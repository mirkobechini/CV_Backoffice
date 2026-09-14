<?php

namespace App\Services;

use App\Models\MileageLog;
use App\Models\Vehicle;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class MileageLogService
{
    /**
     * Registra un chilometraggio noto (da una scadenza rinnovata o da un
     * appuntamento completato) come lettura ufficiale nello storico
     * chilometraggi del veicolo (mileage_logs).
     *
     * Senza questo, il km inserito su una scadenza (last_mileage) o su un
     * appuntamento (mileage_at_service) restava isolato in quel record:
     * non compariva come "ultimo km" nell'indice veicoli, e — più
     * rilevante — il calcolo automatico dello stato scaduto/in scadenza
     * per tagliando/cinghia (che confronta last_mileage+interval_km con il
     * km ATTUALE del veicolo) continuava a basarsi sull'ultimo inserimento
     * manuale nella pagina chilometraggi, anche se più vecchio o assente.
     *
     * Non solleva mai un errore: se la lettura è incoerente con la
     * cronologia esistente (MileageLog::findChronologyConflict, la stessa
     * regola usata dall'inserimento manuale) viene semplicemente saltata,
     * per non rompere lo storico né bloccare l'azione principale (rinnovo
     * scadenza/completamento appuntamento) con un errore di validazione su
     * un campo diverso da quello che l'utente sta effettivamente compilando.
     *
     * @param  bool  $apply  Se false, non scrive nulla: restituisce solo
     *                       cosa farebbe (usato dal comando di backfill per
     *                       l'anteprima).
     * @return string  'created'|'updated'|'unchanged'|'conflict'|'no_mileage'
     */
    public function recordReading(Vehicle $vehicle, string|CarbonInterface $date, ?int $mileage, bool $apply = true): string
    {
        if ($mileage === null) {
            return 'no_mileage';
        }

        $logDate = Carbon::parse($date)->toDateString();

        $existing = MileageLog::where('vehicle_id', $vehicle->id)
            ->whereDate('log_date', $logDate)
            ->first();

        if ($existing) {
            if ((int) $existing->mileage === $mileage) {
                return 'unchanged';
            }

            if (MileageLog::findChronologyConflict($vehicle->id, $logDate, $mileage, $existing->id)) {
                return 'conflict';
            }

            if ($apply) {
                $existing->update(['mileage' => $mileage]);
            }

            return 'updated';
        }

        if (MileageLog::findChronologyConflict($vehicle->id, $logDate, $mileage)) {
            return 'conflict';
        }

        if ($apply) {
            MileageLog::create([
                'vehicle_id' => $vehicle->id,
                'log_date' => $logDate,
                'mileage' => $mileage,
            ]);
        }

        return 'created';
    }
}
