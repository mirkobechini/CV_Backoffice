<?php

namespace App\Services;

use App\Models\Deadline;
use App\Models\Vehicle;
use Carbon\Carbon;

/**
 * Rinnovo di una scadenza periodica SENZA un appuntamento in officina: un
 * veicolo acquistato usato può avere l'ultima revisione/tagliando/cinghia
 * già effettuati dal precedente proprietario, prima che questo sistema
 * iniziasse a tracciare il veicolo — non c'è un intervento da registrare
 * qui dentro, solo la data (e opzionalmente il km) in cui è realmente
 * avvenuto.
 *
 * Estratto da DeadlineService::renewWithoutAppointment() per tenerlo sotto
 * le 400 righe; DeadlineService mantiene un wrapper con lo stesso nome/
 * firma per non toccare i chiamanti esistenti. Dipende da
 * DeadlineOccurrenceCreator (non da DeadlineService) per evitare una
 * dipendenza circolare tra i due servizi.
 */
class DeadlineManualRenewalService
{
    public function __construct(
        private readonly MileageLogService $mileageLogService,
    ) {
    }

    /**
     * Stessa logica di calcolo della prossima occorrenza di
     * MaintenanceRecordController::renewDeadline(), con $renewedDate come
     * base al posto della data di rientro di un appuntamento.
     */
    public function renew(Deadline $deadline, Vehicle $vehicle, Carbon $renewedDate, ?int $mileage = null): Deadline
    {
        $deadline->status = Deadline::STATUS_RENEWED;
        $deadline->is_renewed = true;
        if ($mileage !== null) {
            $deadline->last_mileage = $mileage;
        }
        $deadline->save();

        if ($mileage !== null) {
            $this->mileageLogService->recordReading($vehicle, $renewedDate, $mileage);
        }

        if ($deadline->type === Deadline::TYPE_TAGLIANDO) {
            $dueDate = $renewedDate->copy()->addMonthsNoOverflow(Deadline::TAGLIANDO_INTERVAL_MONTHS);
            $intervalKm = (int) ($vehicle->vehicleType?->regular_tagliando_km ?? 20000);

            DeadlineOccurrenceCreator::create($deadline, $vehicle, $dueDate, [
                'last_mileage' => $mileage,
                'interval_km' => $intervalKm,
                'interval_days' => Deadline::TAGLIANDO_INTERVAL_MONTHS * 30,
            ]);

            return $deadline;
        }

        if ($deadline->type === Deadline::TYPE_CINGHIA) {
            $intervalDays = $vehicle->timingBeltIntervalDays();
            $nextDueDate = $intervalDays ? $renewedDate->copy()->addDays($intervalDays) : null;

            DeadlineOccurrenceCreator::create($deadline, $vehicle, $nextDueDate, [
                'last_mileage' => $mileage ?? 0,
                'interval_km' => Deadline::TIMING_BELT_INTERVAL_KM,
                'interval_days' => $intervalDays,
            ]);

            return $deadline;
        }

        if ($deadline->type === Deadline::TYPE_ASSICURAZIONE) {
            // I dati della polizza (compagnia, numero, premio...) restano
            // gli stessi sulla prossima scadenza finché non viene aggiornata
            // al rinnovo successivo: evita di doverli reinserire da zero.
            $renewalMonths = $deadline->insurance_renewal_months ?? Deadline::INSURANCE_INTERVAL_MONTHS;

            DeadlineOccurrenceCreator::create($deadline, $vehicle, $renewedDate->copy()->addMonthsNoOverflow($renewalMonths), [
                'insurance_company' => $deadline->insurance_company,
                'insurance_policy_number' => $deadline->insurance_policy_number,
                'insurance_premium' => $deadline->insurance_premium,
                'insurance_coverage_type' => $deadline->insurance_coverage_type,
                'insurance_coverage_limit' => $deadline->insurance_coverage_limit,
                'insurance_broker_contact' => $deadline->insurance_broker_contact,
                'insurance_renewal_months' => $deadline->insurance_renewal_months,
            ]);

            return $deadline;
        }

        $nextDueDate = null;
        if ($deadline->type === Deadline::TYPE_MINISTERIAL && ($vehicle->vehicleType?->regular_inspection_months ?? 0) > 0) {
            $nextDueDate = $renewedDate->copy()->addMonthsNoOverflow((int) $vehicle->vehicleType->regular_inspection_months);
        } elseif ($deadline->type === Deadline::TYPE_OXYGEN && Deadline::supportsOxygenCheckForVehicle($vehicle)) {
            $nextDueDate = $renewedDate->copy()->addMonthsNoOverflow(Deadline::OXYGEN_CHECK_INTERVAL_MONTHS);
        }

        if ($nextDueDate) {
            DeadlineOccurrenceCreator::create($deadline, $vehicle, $nextDueDate);
        }

        return $deadline;
    }
}
