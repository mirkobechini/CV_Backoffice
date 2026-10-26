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
    ) {}

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
            [$dueDate, $extra] = DeadlineRenewalCalculator::tagliando($renewedDate, $vehicle, $mileage);
            DeadlineOccurrenceCreator::create($deadline, $vehicle, $dueDate, $extra);

            return $deadline;
        }

        if ($deadline->type === Deadline::TYPE_CINGHIA) {
            [$nextDueDate, $extra] = DeadlineRenewalCalculator::cinghia($renewedDate, $vehicle, $mileage);
            DeadlineOccurrenceCreator::create($deadline, $vehicle, $nextDueDate, $extra);

            return $deadline;
        }

        if ($deadline->type === Deadline::TYPE_ASSICURAZIONE) {
            // I dati della polizza (compagnia, numero, coperture...) restano
            // gli stessi sulla prossima scadenza finché non viene aggiornata
            // al rinnovo successivo: evita di doverli reinserire da zero.
            [$nextDueDate, $extra] = DeadlineRenewalCalculator::insurance($renewedDate, $deadline);
            $next = DeadlineOccurrenceCreator::create($deadline, $vehicle, $nextDueDate, $extra);
            DeadlineOccurrenceCreator::copyInsuranceCoverages($deadline, $next);

            return $deadline;
        }

        $nextDueDate = DeadlineRenewalCalculator::ministerialOrOxygen($renewedDate, $vehicle, $deadline->type);

        if ($nextDueDate) {
            DeadlineOccurrenceCreator::create($deadline, $vehicle, $nextDueDate);
        }

        return $deadline;
    }
}
