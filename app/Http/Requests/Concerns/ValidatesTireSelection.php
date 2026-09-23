<?php

namespace App\Http\Requests\Concerns;

use App\Models\MaintenanceRecord;
use App\Models\Tire;
use Illuminate\Validation\Validator;

/**
 * Regole condivise tra Store/UpdateMaintenanceRecordRequest per un
 * appuntamento "Cambio Gomme": le gomme scelte insieme (esistenti
 * selezionate + eventuali nuove descritte) possono essere 1, 2 o 4 — non
 * più vincolate a un set completo — ma devono avere tutte la stessa
 * stagionalità e occupare ciascuna una posizione diversa (non ha senso
 * cambiare "anteriore sinistra" due volte nello stesso appuntamento).
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
                ->get(['position', 'season'])
                ->map(fn (Tire $tire) => ['position' => $tire->position, 'season' => $tire->season])
                ->all();
        }

        if ($this->input('new_tire_season')) {
            $positions = Tire::positionsForGroup($this->input('new_tire_group'), $this->input('new_tire_position'));
            foreach ($positions as $position) {
                $selections[] = ['position' => $position, 'season' => $this->input('new_tire_season')];
            }
        }

        if (empty($selections)) {
            return;
        }

        $distinctSeasons = array_unique(array_column($selections, 'season'));
        $positions = array_column($selections, 'position');

        if (count($distinctSeasons) > 1) {
            $validator->errors()->add('target_tire_ids', 'Le gomme scelte insieme devono avere tutte la stessa stagionalità (non mischiare estive/invernali/quattro stagioni).');
        }

        if (count($positions) !== count(array_unique($positions))) {
            $validator->errors()->add('target_tire_ids', 'Non puoi selezionare più di una gomma per la stessa posizione nello stesso appuntamento.');
        }
    }
}
