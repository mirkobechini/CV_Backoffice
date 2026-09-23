<?php

namespace App\Services;

use App\Models\Tire;
use App\Models\TireChange;
use Illuminate\Support\Carbon;

/**
 * Registra il montaggio di una gomma su un veicolo: smonta quella
 * eventualmente già montata nella stessa posizione (front_left/front_right/
 * rear_left/rear_right), applicandole la disposizione scelta.
 *
 * Condivisa tra TireController::recordChange() (azione manuale dalla scheda
 * pneumatico) e MaintenanceRecordController::complete() (appuntamento
 * "Cambio Gomme"), per non duplicare questa logica in due posti.
 */
class TireChangeService
{
    public function recordChange(
        Tire $tireToMount,
        Carbon $changedDate,
        ?int $mileageAtChange,
        string $previousDisposition,
        ?string $notes = null,
    ): TireChange {
        $vehicle = $tireToMount->vehicle;

        $previousTire = $vehicle->tires()
            ->where('status', Tire::STATUS_MOUNTED)
            ->where('position', $tireToMount->position)
            ->where('id', '!=', $tireToMount->id)
            ->first();

        $previousTire?->update(['status' => $previousDisposition]);

        $tireToMount->update([
            'status' => Tire::STATUS_MOUNTED,
            'mounted_date' => $changedDate,
            'mounted_mileage' => $mileageAtChange ?? $tireToMount->mounted_mileage,
        ]);

        return $vehicle->tireChanges()->create([
            'tire_id' => $tireToMount->id,
            'previous_tire_id' => $previousTire?->id,
            'changed_date' => $changedDate,
            'mileage_at_change' => $mileageAtChange,
            'notes' => $notes,
        ]);
    }
}
