<?php

namespace App\Observers;

use App\Models\Deadline;
use App\Models\Vehicle;
use Carbon\Carbon;

class VehicleObserver
{
    private const MAX_BACKFILL_ITERATIONS = 10;

    public function created(Vehicle $vehicle): void
    {
        $vehicle->loadMissing('vehicleType');

        if (!$vehicle->vehicleType || !$vehicle->immatricolation_date) {
            return;
        }

        $firstInspectionMonths = (int) $vehicle->vehicleType->first_inspection_months;
        $regularInspectionMonths = (int) $vehicle->vehicleType->regular_inspection_months;

        if ($firstInspectionMonths <= 0 || $regularInspectionMonths <= 0) {
            return;
        }

        $today = Carbon::today();
        $dueDate = Carbon::parse($vehicle->immatricolation_date)->addMonthsNoOverflow($firstInspectionMonths);

        // Backfill storico: scadenze già passate, con safety limit per veicoli molto vecchi.
        $iteration = 0;
        while ($dueDate->lte($today) && $iteration < self::MAX_BACKFILL_ITERATIONS) {
            $this->createDeadlineIfMissing($vehicle, Deadline::TYPE_MINISTERIAL, $dueDate, true);
            $dueDate->addMonthsNoOverflow($regularInspectionMonths);
            $iteration++;
        }

        // Prima scadenza futura/aperta (solo se non abbiamo già superato il limite)
        if ($iteration < self::MAX_BACKFILL_ITERATIONS) {
            $this->createDeadlineIfMissing($vehicle, Deadline::TYPE_MINISTERIAL, $dueDate, false);
        }

        if (!Deadline::supportsOxygenCheckForVehicle($vehicle)) {
            return;
        }

        $iteration = 0;
        $oxygenDueDate = Carbon::parse($vehicle->immatricolation_date)
            ->addMonthsNoOverflow(Deadline::OXYGEN_CHECK_INTERVAL_MONTHS);

        while ($oxygenDueDate->lte($today) && $iteration < self::MAX_BACKFILL_ITERATIONS) {
            $this->createDeadlineIfMissing($vehicle, Deadline::TYPE_OXYGEN, $oxygenDueDate, true);
            $oxygenDueDate->addMonthsNoOverflow(Deadline::OXYGEN_CHECK_INTERVAL_MONTHS);
            $iteration++;
        }

        if ($iteration < self::MAX_BACKFILL_ITERATIONS) {
            $this->createDeadlineIfMissing($vehicle, Deadline::TYPE_OXYGEN, $oxygenDueDate, false);
        }

        // Cinghia distribuzione: se il veicolo ne è dotato, genera la scadenza
        // (10 anni o 100.000 km dall'immatricolazione / precedente cambio).
        if ($vehicle->has_timing_belt) {
            $this->createTimingBeltDeadline($vehicle);
        }

        // Primo tagliando: scade al raggiungimento dei km configurati
        // (default 25.000 km da veicolo nuovo).
        $this->createFirstTagliandoDeadline($vehicle);
    }

    /**
     * Crea la scadenza del primo tagliando se non esiste già.
     * Scade al raggiungimento dei km configurati sul tipo veicolo
     * (default 25.000 km da veicolo nuovo).
     */
    private function createFirstTagliandoDeadline(Vehicle $vehicle): void
    {
        $alreadyExists = $vehicle->deadlines()
            ->where('type', Deadline::TYPE_TAGLIANDO)
            ->exists();

        if ($alreadyExists) {
            return;
        }

        $firstKm = (int) ($vehicle->vehicleType?->first_tagliando_km ?? 25000);

        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'interval_km' => $firstKm,
            'last_mileage' => 0,
        ]);
    }

    /**
     * Crea la scadenza della cinghia di distribuzione se non esiste già.
     * Scade al primo tra: 10 anni (3650 giorni) o 100.000 km.
     */
    private function createTimingBeltDeadline(Vehicle $vehicle): void
    {
        $alreadyExists = $vehicle->deadlines()
            ->where('type', Deadline::TYPE_CINGHIA)
            ->exists();

        if ($alreadyExists) {
            return;
        }

        $dueDate = Carbon::parse($vehicle->immatricolation_date)
            ->addDays(Deadline::TIMING_BELT_INTERVAL_DAYS);

        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
            'due_date' => $dueDate->toDateString(),
            'interval_km' => Deadline::TIMING_BELT_INTERVAL_KM,
            'last_mileage' => 0,
            'interval_days' => Deadline::TIMING_BELT_INTERVAL_DAYS,
        ]);
    }

    private function createDeadlineIfMissing(Vehicle $vehicle, string $type, Carbon $dueDate, bool $renewed = false): void
    {
        $alreadyExists = $vehicle->deadlines()
            ->where('type', $type)
            ->whereDate('due_date', $dueDate->toDateString())
            ->exists();

        if ($alreadyExists) {
            return;
        }

        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => $type,
            'due_date' => $dueDate->toDateString(),
            'is_renewed' => $renewed,
        ]);
        $deadline->syncStatusFromRules();
    }
}
