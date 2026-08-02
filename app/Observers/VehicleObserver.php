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
