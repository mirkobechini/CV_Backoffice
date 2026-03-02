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

        while ($dueDate->lte($today)) {
            $alreadyExists = Deadline::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('type', Deadline::TYPE_MINISTERIAL)
                ->whereDate('due_date', $dueDate->toDateString())
                ->exists();

            if (!$alreadyExists) {
                Deadline::create([
                    'vehicle_id' => $vehicle->id,
                    'type' => Deadline::TYPE_MINISTERIAL,
                    'due_date' => $dueDate->toDateString(),
                    'status' => 'renewed',
                ]);
            }

            $dueDate->addMonthsNoOverflow($regularInspectionMonths);
        }
    }
}
