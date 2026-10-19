<?php

namespace App\Services;

use App\Models\Deadline;
use App\Models\Vehicle;
use Carbon\Carbon;

/**
 * Calcolo della prossima data/km di scadenza per i tipi periodici
 * (tagliando/cinghia/ministeriale/ossigeno), condiviso tra
 * MaintenanceCompletionService (rinnovo da appuntamento completato, base =
 * data di rientro) e DeadlineManualRenewalService (rinnovo senza
 * appuntamento, base = data inserita a mano) — stesso calcolo, prima
 * duplicato quasi identico in entrambi (audit 2026-10-19). Restituisce solo
 * [dueDate, extra]: la creazione vera e propria (con guardia anti-duplicati)
 * resta a DeadlineOccurrenceCreator::create(), chiamato dai due servizi.
 */
class DeadlineRenewalCalculator
{
    /**
     * @return array{0: ?Carbon, 1: array<string, mixed>}
     */
    public static function tagliando(Carbon $baseDate, Vehicle $vehicle, ?int $baseKm): array
    {
        $dueDate = $baseDate->copy()->addMonthsNoOverflow(Deadline::TAGLIANDO_INTERVAL_MONTHS);
        $intervalKm = (int) ($vehicle->vehicleType?->regular_tagliando_km ?? 20000);

        return [$dueDate, [
            'last_mileage' => $baseKm,
            'interval_km' => $intervalKm,
            'interval_days' => Deadline::TAGLIANDO_INTERVAL_MONTHS * 30,
        ]];
    }

    /**
     * @return array{0: ?Carbon, 1: array<string, mixed>}
     */
    public static function cinghia(Carbon $baseDate, Vehicle $vehicle, ?int $baseKm): array
    {
        $intervalDays = $vehicle->timingBeltIntervalDays();
        $dueDate = $intervalDays ? $baseDate->copy()->addDays($intervalDays) : null;

        return [$dueDate, [
            'last_mileage' => $baseKm ?? 0,
            'interval_km' => Deadline::TIMING_BELT_INTERVAL_KM,
            'interval_days' => $intervalDays,
        ]];
    }

    public static function ministerial(Carbon $baseDate, Vehicle $vehicle): ?Carbon
    {
        $months = (int) ($vehicle->vehicleType?->regular_inspection_months ?? 0);

        return $months > 0 ? $baseDate->copy()->addMonthsNoOverflow($months) : null;
    }

    public static function oxygen(Carbon $baseDate, Vehicle $vehicle): ?Carbon
    {
        return Deadline::supportsOxygenCheckForVehicle($vehicle)
            ? $baseDate->copy()->addMonthsNoOverflow(Deadline::OXYGEN_CHECK_INTERVAL_MONTHS)
            : null;
    }
}
