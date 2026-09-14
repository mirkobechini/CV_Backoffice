<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Models\Vehicle;

/**
 * Come HasRoleBasedAccess (capo/sottocapo gestiscono, i membri solo
 * vedono), ma per i modelli legati a un veicolo (o il veicolo stesso):
 * verifica anche che il record appartenga al gruppo attivo dell'utente.
 *
 * Senza questo controllo, l'isolamento tra gruppi esisteva solo a livello
 * di query nelle liste/tendine (Vehicle::forCurrentUser()), mai
 * nell'autorizzazione sul singolo record: un utente autenticato in un
 * gruppo poteva vedere, e se capo/sottocapo modificare o eliminare, i dati
 * di qualsiasi altro gruppo semplicemente conoscendone l'id.
 *
 * Non si può decidere nel before(): prima approvava sempre chi può gestire
 * i dati (canManageData()) a prescindere dal modello, che è proprio la
 * falla da chiudere. before() qui interviene solo per "create", l'unica
 * abilità senza un record esistente da verificare.
 */
trait HasGroupScopedAccess
{
    public function before(User $user, string $ability): ?bool
    {
        if ($ability === 'create') {
            return $user->canManageData() ?: null;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true; // le liste sono già filtrate per gruppo dalle query dei controller
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $this->belongsToUserGroup($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->canManageData();
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $user->canManageData() && $this->belongsToUserGroup($user, $model);
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $user->canManageData() && $this->belongsToUserGroup($user, $model);
    }

    public function restore(User $user, mixed $model = null): bool
    {
        return $this->delete($user, $model);
    }

    public function forceDelete(User $user, mixed $model = null): bool
    {
        return $this->delete($user, $model);
    }

    /**
     * Stessa regola di Vehicle::scopeForCurrentUser(): un utente senza
     * gruppo attivo non viene ristretto (vede/gestisce tutto), e un utente
     * con un gruppo attivo può accedere solo ai record il cui veicolo
     * appartiene a quel gruppo. Un record senza veicolo collegato (es.
     * un'attrezzatura non ancora assegnata) non appartiene a nessun gruppo
     * specifico e resta visibile a tutti, coerentemente con come i
     * controller filtrano le rispettive liste.
     */
    protected function belongsToUserGroup(User $user, mixed $model): bool
    {
        // Un'autorizzazione "di classe" (es. authorize('delete',
        // MileageLog::class) per un'azione collettiva/di massa) non ha
        // un'istanza specifica da verificare: Laravel in questo caso invoca
        // il metodo della policy con il solo $user (né istanza né stringa
        // di classe come secondo argomento, da cui il default null sopra).
        // Il controllo di gruppo sui singoli record coinvolti resta
        // responsabilità del controller.
        if ($model === null || is_string($model)) {
            return true;
        }

        $groupId = $user->activeGroup()?->id;

        if (! $groupId) {
            return true;
        }

        if ($model instanceof Vehicle) {
            return $model->group_id === $groupId;
        }

        $vehicle = $model->vehicle;

        if (! $vehicle) {
            return true;
        }

        return $vehicle->group_id === $groupId;
    }
}
