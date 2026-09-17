<?php

namespace App\Http\Requests\Concerns;

use App\Models\MaintenanceRecord;
use App\Models\Tire;
use Illuminate\Validation\Validator;

/**
 * Regole condivise tra Store/UpdateMaintenanceRecordRequest per un
 * appuntamento "Cambio Gomme": i set scelti insieme (esistenti selezionati
 * + eventuale nuovo set descritto) devono coprire esattamente 4 gomme e
 * avere tutti la stessa stagionalità, altrimenti il "set" risultante non
 * ha senso (es. 2 invernali + 2 estive, o 6 gomme totali).
 */
trait ValidatesTireSelection
{
    protected function validateTireSelection(Validator $validator): void
    {
        if ($this->input('activity_type') !== MaintenanceRecord::ACTIVITY_TIRE_CHANGE) {
            return;
        }

        $targetTireIds = $this->input('target_tire_ids', []);
        $selections = [];

        if (! empty($targetTireIds)) {
            $selections = Tire::whereIn('id', $targetTireIds)
                ->get(['quantity', 'season'])
                ->map(fn (Tire $tire) => ['quantity' => $tire->quantity, 'season' => $tire->season])
                ->all();
        }

        if ($this->input('new_tire_season')) {
            $selections[] = [
                'quantity' => (int) ($this->input('new_tire_quantity') ?: 4),
                'season' => $this->input('new_tire_season'),
            ];
        }

        if (empty($selections)) {
            return;
        }

        $totalQuantity = array_sum(array_column($selections, 'quantity'));
        $distinctSeasons = array_unique(array_column($selections, 'season'));

        if ($totalQuantity !== 4) {
            $validator->errors()->add('target_tire_ids', "I set scelti insieme devono coprire esattamente 4 gomme (attualmente {$totalQuantity}).");
        }

        if (count($distinctSeasons) > 1) {
            $validator->errors()->add('target_tire_ids', 'I set scelti insieme devono avere tutti la stessa stagionalità (non mischiare estive/invernali/quattro stagioni).');
        }
    }
}
