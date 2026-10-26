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

    /**
     * Dispatch ministeriale/ossigeno, gli unici due tipi che condividono lo
     * stesso identico match (audit 2026-10-22, Q4): prima reimplementato in
     * 3 punti (qui, DeadlineManualRenewalService, MaintenanceCompletionService).
     */
    public static function ministerialOrOxygen(Carbon $baseDate, Vehicle $vehicle, string $type): ?Carbon
    {
        return match ($type) {
            Deadline::TYPE_MINISTERIAL => self::ministerial($baseDate, $vehicle),
            Deadline::TYPE_OXYGEN => self::oxygen($baseDate, $vehicle),
            default => null,
        };
    }

    /**
     * Prossima data + dati polizza da riportare, dopo il rinnovo di
     * un'Assicurazione (audit 2026-10-22, Q4): prima duplicato in
     * DeadlineManualRenewalService e DeadlineService.
     *
     * @return array{0: Carbon, 1: array<string, mixed>}
     */
    public static function insurance(Carbon $baseDate, Deadline $deadline): array
    {
        $renewalMonths = $deadline->insurance_renewal_months ?? Deadline::INSURANCE_INTERVAL_MONTHS;

        return [$baseDate->copy()->addMonthsNoOverflow($renewalMonths), [
            'insurance_company' => $deadline->insurance_company,
            'insurance_policy_number' => $deadline->insurance_policy_number,
            'insurance_coverage_limit' => $deadline->insurance_coverage_limit,
            'insurance_broker_contact' => $deadline->insurance_broker_contact,
            'insurance_renewal_months' => $deadline->insurance_renewal_months,
        ]];
    }
}
