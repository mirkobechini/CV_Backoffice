<?php

namespace App\Services;

use App\Models\Tire;
use App\Models\TireChange;
use Illuminate\Support\Carbon;

/**
 * Registra il montaggio di un set di gomme (o di un solo asse) su un
 * veicolo, gestendo lo smontaggio/split di ciò che era montato prima.
 *
 * Condivisa tra TireController::recordChange() (azione manuale dalla scheda
 * pneumatico) e MaintenanceRecordController::complete() (appuntamento
 * "Cambio Gomme"), per non duplicare questa logica in due posti.
 */
class TireChangeService
{
    /**
     * Monta $tireToMount sul veicolo, aggiornando ciò che era montato
     * prima secondo l'asse coinvolto:
     * - un nuovo set "full" sostituisce tutto ciò che è montato;
     * - un nuovo set "front"/"rear" sostituisce solo l'asse corrispondente
     *   già tracciato separatamente; se il veicolo aveva invece un set
     *   "full" montato, questo viene "spaccato" in due: l'asse non toccato
     *   resta montato (stessi dati, nuovo record), l'altro viene chiuso
     *   come superato (non si applica $previousDisposition: non è una
     *   rimozione fisica scelta dall'utente, solo metà del set è stata
     *   davvero tolta).
     */
    public function recordChange(
        Tire $tireToMount,
        Carbon $changedDate,
        ?int $mileageAtChange,
        string $previousDisposition,
        ?string $notes = null,
    ): TireChange {
        $vehicle = $tireToMount->vehicle;
        $axle = $tireToMount->axle;

        $currentlyMounted = $vehicle->tires()
            ->where('status', Tire::STATUS_MOUNTED)
            ->where('id', '!=', $tireToMount->id)
            ->get();

        $previousTireForHistory = null;

        foreach ($currentlyMounted as $existing) {
            if ($axle === Tire::AXLE_FULL || $existing->axle === $axle) {
                $existing->update(['status' => $previousDisposition]);
                $previousTireForHistory ??= $existing;

                continue;
            }

            if ($existing->axle === Tire::AXLE_FULL) {
                $otherAxle = $axle === Tire::AXLE_FRONT ? Tire::AXLE_REAR : Tire::AXLE_FRONT;

                $split = $existing->replicate();
                $split->axle = $otherAxle;
                $split->quantity = 2;
                $split->status = Tire::STATUS_MOUNTED;
                $split->save();

                $existing->update(['status' => Tire::STATUS_RETIRED]);
                $previousTireForHistory ??= $existing;
            }

            // Asse diverso, non "full": resta montato senza modifiche
            // (es. si sostituiscono le anteriori, le posteriori restano).
        }

        $tireToMount->update([
            'status' => Tire::STATUS_MOUNTED,
            'mounted_date' => $changedDate,
            'mounted_mileage' => $mileageAtChange ?? $tireToMount->mounted_mileage,
        ]);

        return $vehicle->tireChanges()->create([
            'tire_id' => $tireToMount->id,
            'previous_tire_id' => $previousTireForHistory?->id,
            'changed_date' => $changedDate,
            'mileage_at_change' => $mileageAtChange,
            'notes' => $notes,
        ]);
    }
}
