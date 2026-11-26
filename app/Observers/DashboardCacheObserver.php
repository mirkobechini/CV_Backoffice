<?php

namespace App\Observers;

use App\Models\Group;
use App\Models\Vehicle;
use App\Services\DashboardCache;

/**
 * Invalida la cache dashboard (5 minuti, vedi DashboardController) quando
 * cambia un dato che ci compare dentro: senza questo, una mutazione (es. il
 * rinnovo di una scadenza) restava invisibile in dashboard fino alla
 * scadenza naturale della cache, anche se già visibile ovunque altrove
 * nell'app.
 *
 * Registrato per tutti i modelli i cui dati alimentano la dashboard
 * (Deadline, Issue, MaintenanceRecord, Equipment, Tire, MileageLog,
 * Vehicle, Group): vedi AppServiceProvider::boot().
 */
class DashboardCacheObserver
{
    public function saved(mixed $model): void
    {
        // Una copia fresca, non $model stesso: leggere ->vehicle/
        // ->involvedGroupIds() in forget() caricherebbe (e cachierebbe) le
        // relazioni many-to-many sull'istanza passata all'evento "saved" —
        // se il chiamante sta per collegare quelle stesse relazioni subito
        // dopo (es. ->equipments()->attach()/sync(), che non riemette un
        // evento "saved"), si ritroverebbe con una cache obsoleta per il
        // resto della richiesta (bug trovato scrivendo i test di questo
        // fix, audit 2026-11-25 B2). Non rilevante per deleted() sotto: a
        // quel punto nessun altro collegamento viene più fatto su
        // quell'istanza.
        $this->forget($model->fresh() ?? $model);
    }

    public function deleted(mixed $model): void
    {
        $this->forget($model);
    }

    private function forget(mixed $model): void
    {
        if ($model instanceof Group) {
            DashboardCache::forgetForGroup($model->id);

            return;
        }

        if ($model instanceof Vehicle) {
            DashboardCache::forgetForGroup($model->group_id);

            return;
        }

        $groupId = $model->vehicle?->group_id;

        if ($groupId) {
            DashboardCache::forgetForGroup($groupId);

            return;
        }

        // Nessun gruppo univoco (es. EquipmentIssue/EquipmentMaintenanceRecord
        // di un'attrezzatura non assegnata, o con attrezzature di più
        // gruppi diversi): invalidare solo il bucket "none" lasciava le
        // dashboard dei gruppi reali che la vedono ferme fino a 5 minuti
        // (audit 2026-11-25, B2). I modelli che possono essere ambigui per
        // più gruppi specifici (EquipmentMaintenanceRecord) espongono
        // involvedGroupIds(): se non vuoto, invalida solo quei gruppi;
        // altrimenti (nessuna attrezzatura assegnata, o il modello non
        // espone il metodo) il dato è visibile a tutti, quindi invalida
        // tutti i gruppi.
        $involvedGroupIds = method_exists($model, 'involvedGroupIds')
            ? $model->involvedGroupIds()
            : collect();

        if ($involvedGroupIds->isNotEmpty()) {
            $involvedGroupIds->each(fn ($id) => DashboardCache::forgetForGroup($id));

            return;
        }

        Group::pluck('id')->each(fn ($id) => DashboardCache::forgetForGroup($id));
        DashboardCache::forgetForGroup(null);
    }
}
