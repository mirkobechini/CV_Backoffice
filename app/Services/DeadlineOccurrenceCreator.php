<?php

namespace App\Services;

use App\Models\Deadline;
use App\Models\Vehicle;
use Carbon\Carbon;

/**
 * Creazione della scadenza che rinnova un'altra, con guardia anti-duplicati.
 * Estratto da DeadlineService::createNextOccurrence() per tenerlo sotto le
 * 400 righe e per poter essere usato anche da DeadlineManualRenewalService
 * senza introdurre una dipendenza circolare tra i due servizi (nessuna
 * delle due classi dipende dall'altra: entrambe dipendono solo da questa,
 * che non dipende da nessuna delle due).
 */
class DeadlineOccurrenceCreator
{
    /**
     * $extra permette di impostare altri campi sulla nuova scadenza (es.
     * last_mileage/interval_km per tagliando e cinghia) nella stessa
     * chiamata, senza un update separato.
     */
    public static function create(Deadline $renewedDeadline, Vehicle $vehicle, ?Carbon $nextDueDate, array $extra = []): ?Deadline
    {
        // Se una scadenza dello stesso tipo rinnova già questa
        // (renews_deadline_id), non ne creiamo un'altra: evita duplicati
        // anche quando il ricalcolo della data differisce leggermente da
        // quella già creata, aggirando il matching per data esatta di
        // firstOrCreate() più sotto.
        $alreadyHasNext = Deadline::where('renews_deadline_id', $renewedDeadline->id)
            ->where('type', $renewedDeadline->type)
            ->exists();

        if ($alreadyHasNext) {
            return null;
        }

        // renews_deadline_id nel match: senza, due occorrenze consecutive di
        // una scadenza SOLO a km (due_date sempre null, es. cinghia a secco)
        // avrebbero lo stesso vehicle_id+type+due_date e firstOrCreate
        // ritornerebbe semplicemente quella vecchia invece di crearne una
        // nuova.
        return Deadline::firstOrCreate(
            [
                'vehicle_id' => $vehicle->id,
                'type' => $renewedDeadline->type,
                'due_date' => $nextDueDate?->toDateString(),
                'renews_deadline_id' => $renewedDeadline->id,
            ],
            array_merge([
                'status' => Deadline::STATUS_PENDING,
                'renews_deadline_id' => $renewedDeadline->id,
            ], $extra)
        );
    }
}
