<?php

namespace App\Rules;

use App\Models\Vehicle;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Verifica che l'id indicato esista E appartenga al gruppo attivo
 * dell'utente autenticato — stessa logica di Vehicle::scopeForCurrentUser(),
 * applicata in validazione invece che solo in lettura.
 *
 * Senza questa regola, una semplice "exists:tabella,id" accettava l'id di
 * QUALSIASI record dell'intero database: un capo/sottocapo del proprio
 * gruppo poteva collegare gomme, guasti, scadenze, interventi o
 * attrezzature al veicolo di un ALTRO gruppo semplicemente indovinandone
 * l'id (sequenziale), scavalcando l'isolamento tra gruppi anche se le
 * query di lettura restavano correttamente filtrate.
 */
class BelongsToCurrentUserGroup implements ValidationRule
{
    public function __construct(
        private readonly string $modelClass,
        private readonly string $vehicleRelation = 'vehicle',
        private readonly ?string $message = null,
        /**
         * Per i modelli con un group_id proprio (es. FleetDevice, FuelCard:
         * possono esistere senza un veicolo collegato — jolly, non ancora
         * assegnati — e whereHas('vehicle', ...) li escluderebbe sempre,
         * anche quando appartengono correttamente al gruppo attivo).
         */
        private readonly bool $byOwnGroupColumn = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = $this->modelClass::query();

        if (is_a($this->modelClass, Vehicle::class, true) || $this->byOwnGroupColumn) {
            $groupId = auth()->user()?->activeGroup()?->id;
            $query->where('group_id', $groupId);
        } else {
            $query->whereHas($this->vehicleRelation, fn ($q) => $q->forCurrentUser());
        }

        if (! $query->whereKey($value)->exists()) {
            $fail($this->message ?? 'Il valore selezionato non esiste o non appartiene al tuo gruppo.');
        }
    }
}
