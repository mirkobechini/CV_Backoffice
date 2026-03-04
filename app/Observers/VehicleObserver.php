<?php

namespace App\Observers;

use App\Models\Deadline;
use App\Models\Vehicle;
use Carbon\Carbon;

class VehicleObserver
{
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

        // Backfill storico: tutte le scadenze già passate vengono marcate renewed.
        while ($dueDate->lte($today)) {
            $this->createDeadlineIfMissing($vehicle, Deadline::TYPE_MINISTERIAL, $dueDate, 'renewed');

            $dueDate->addMonthsNoOverflow($regularInspectionMonths);
        }

        // Prima scadenza futura/aperta.
        $this->createDeadlineIfMissing($vehicle, Deadline::TYPE_MINISTERIAL, $dueDate, 'pending');

        if (!Deadline::supportsOxygenCheckForVehicle($vehicle)) {
            return;
        }

        $oxygenDueDate = Carbon::parse($vehicle->immatricolation_date)
            ->addMonthsNoOverflow(Deadline::OXYGEN_CHECK_INTERVAL_MONTHS);

        // Stesso approccio per revisione ossigeno, solo per mezzi che la supportano.
        while ($oxygenDueDate->lte($today)) {
            $this->createDeadlineIfMissing($vehicle, Deadline::TYPE_OXYGEN, $oxygenDueDate, 'renewed');

            $oxygenDueDate->addMonthsNoOverflow(Deadline::OXYGEN_CHECK_INTERVAL_MONTHS);
        }

        $this->createDeadlineIfMissing($vehicle, Deadline::TYPE_OXYGEN, $oxygenDueDate, 'pending');
    }

    private function createDeadlineIfMissing(Vehicle $vehicle, string $type, Carbon $dueDate, string $status): void
    {
        $alreadyExists = Deadline::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('type', $type)
            ->whereDate('due_date', $dueDate->toDateString())
            ->exists();

        if ($alreadyExists) {
            return;
        }

        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => $type,
            'due_date' => $dueDate->toDateString(),
            'status' => $status,
        ]);
    }
}
