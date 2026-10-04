<?php

namespace App\Services;

use App\Models\Deadline;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;

/**
 * Calcolo della prossima data di scadenza per tipo (ministeriale, ossigeno,
 * tagliando): dall'ultima scadenza rinnovata di quel tipo per il veicolo,
 * altrimenti dalla data di immatricolazione. Estratto da Deadline per
 * tenere il model sotto le 400 righe; Deadline mantiene wrapper statici
 * con lo stesso nome per non cambiare l'API esistente.
 */
class DeadlineDueDateCalculator
{
    public static function calculateMinisterialDueDateForVehicle(Vehicle $vehicle, ?int $excludeDeadlineId = null): ?Carbon
    {
        if (! $vehicle->immatricolation_date || ! $vehicle->vehicleType) {
            return null;
        }

        $query = $vehicle->deadlines()
            ->where('type', Deadline::TYPE_MINISTERIAL)
            ->where('status', Deadline::STATUS_RENEWED)
            ->orderByDesc('due_date');

        if ($excludeDeadlineId !== null) {
            $query->where('id', '!=', $excludeDeadlineId);
        }

        $lastRenewedDeadline = $query->first();

        // Se c'è una revisione rinnovata precedente, calcoliamo la successiva da quella;
        // altrimenti partiamo dalla data di immatricolazione con intervallo iniziale.
        if ($lastRenewedDeadline && $lastRenewedDeadline->due_date) {
            $monthsToAdd = (int) $vehicle->vehicleType->regular_inspection_months;

            return Carbon::parse($lastRenewedDeadline->due_date)->addMonthsNoOverflow($monthsToAdd);
        }

        $monthsToAdd = (int) $vehicle->vehicleType->first_inspection_months;

        return Carbon::parse($vehicle->immatricolation_date)->addMonthsNoOverflow($monthsToAdd);
    }

    public static function calculateOxygenDueDateForVehicle(Vehicle $vehicle, ?int $excludeDeadlineId = null): ?Carbon
    {
        if (! $vehicle->immatricolation_date || ! self::supportsOxygenCheckForVehicle($vehicle)) {
            return null;
        }

        $query = $vehicle->deadlines()
            ->where('type', Deadline::TYPE_OXYGEN)
            ->where('status', Deadline::STATUS_RENEWED)
            ->orderByDesc('due_date');

        if ($excludeDeadlineId !== null) {
            $query->where('id', '!=', $excludeDeadlineId);
        }

        $lastRenewedDeadline = $query->first();

        if ($lastRenewedDeadline && $lastRenewedDeadline->due_date) {
            return Carbon::parse($lastRenewedDeadline->due_date)
                ->addMonthsNoOverflow(Deadline::OXYGEN_CHECK_INTERVAL_MONTHS);
        }

        return Carbon::parse($vehicle->immatricolation_date)
            ->addMonthsNoOverflow(Deadline::OXYGEN_CHECK_INTERVAL_MONTHS);
    }

    public static function supportsOxygenCheckForVehicle(Vehicle $vehicle): bool
    {
        return (bool) optional($vehicle->vehicleType)->needs_oxygen_check;
    }

    /**
     * Calcola la data di scadenza del prossimo tagliando.
     * Usa l'ultimo tagliando rinnovato come base, altrimenti l'immatricolazione.
     */
    public static function calculateTagliandoDueDateForVehicle(Vehicle $vehicle, ?int $excludeDeadlineId = null): ?Carbon
    {
        if (! $vehicle->immatricolation_date) {
            return null;
        }

        $query = $vehicle->deadlines()
            ->where('type', Deadline::TYPE_TAGLIANDO)
            ->where('status', Deadline::STATUS_RENEWED)
            ->orderByDesc('due_date');

        if ($excludeDeadlineId !== null) {
            $query->where('id', '!=', $excludeDeadlineId);
        }

        $lastRenewedDeadline = $query->first();

        if ($lastRenewedDeadline && $lastRenewedDeadline->due_date) {
            return Carbon::parse($lastRenewedDeadline->due_date)
                ->addMonthsNoOverflow(Deadline::TAGLIANDO_INTERVAL_MONTHS);
        }

        return Carbon::parse($vehicle->immatricolation_date)
            ->addMonthsNoOverflow(Deadline::TAGLIANDO_INTERVAL_MONTHS);
    }
}
