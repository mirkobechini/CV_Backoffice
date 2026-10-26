<?php

namespace App\Http\Requests;

use App\Models\FuelCard;

/**
 * Identica a StoreFuelCardRequest tranne per prepareForValidation(), che
 * in modifica deve gestire il caso di un PATCH che omette vehicle_id
 * (audit 2026-10-22, Q6/B9): prima duplicata per intero invece di
 * estendere.
 */
class UpdateFuelCardRequest extends StoreFuelCardRequest
{
    /**
     * Se vehicle_id è del tutto assente dalla richiesta (non solo vuoto) e
     * lo stato è jolly/non assegnata, lo imposta esplicitamente a null
     * (audit 2026-10-22, B9): un PATCH che omettesse il campo (invece di
     * inviarlo vuoto, come fa il form reale) lasciava invariato il valore
     * esistente su $fuelCard->update(), perché Eloquent aggiorna solo le
     * chiavi presenti nell'array validato. Un vehicle_id inviato
     * esplicitamente ma in conflitto con lo stato resta invece rifiutato
     * dalla validazione (ereditata da StoreFuelCardRequest::withValidator()),
     * non silenziosamente azzerato.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('vehicle_id')
            && in_array($this->input('status'), [FuelCard::STATUS_JOLLY, FuelCard::STATUS_UNASSIGNED], true)) {
            $this->merge(['vehicle_id' => null]);
        }
    }
}
