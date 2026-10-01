<?php

namespace App\Services;

use App\Models\Deadline;
use App\Models\MaintenanceRecord;
use App\Models\Tire;

class MaintenanceCompletionService
{
    public function __construct(
        private readonly DeadlineService $deadlineService,
        private readonly MileageLogService $mileageLogService,
        private readonly TireChangeService $tireChangeService,
    ) {
    }

    /**
     * Per un appuntamento "Cambio Gomme", collega le gomme da montare: una
     * o più esistenti (in magazzino, 1/2/4, validate in
     * ValidatesTireSelection) e/o di nuove appena descritte. Il montaggio
     * vero e proprio (con la scelta di cosa fare delle gomme sostituite)
     * avviene solo al completamento dell'appuntamento (vedi complete()),
     * non qui.
     */
    public function linkTireItems(MaintenanceRecord $maintenanceRecord, array $data): void
    {
        if (($data['activity_type'] ?? null) !== MaintenanceRecord::ACTIVITY_TIRE_CHANGE) {
            return;
        }

        $tires = collect();

        if (! empty($data['target_tire_ids'])) {
            $tires = Tire::whereIn('id', $data['target_tire_ids'])
                ->where('vehicle_id', $data['vehicle_id'])
                ->get();
        }

        if (! empty($data['new_tire_season'])) {
            $vehicle = $maintenanceRecord->vehicle;

            foreach (Tire::positionsForGroup($data['new_tire_group'] ?? null, $data['new_tire_position'] ?? null) as $position) {
                $tires->push(Tire::create([
                    'vehicle_id' => $data['vehicle_id'],
                    'season' => $data['new_tire_season'],
                    'position' => $position,
                    'brand' => $data['new_tire_brand'] ?? null,
                    'model_name' => $data['new_tire_model_name'] ?? null,
                    'size' => $data['new_tire_size'] ?? null,
                    'status' => Tire::STATUS_STORED,
                ]));
            }

            if ($warning = Tire::sizeMismatchWarning($data['new_tire_size'] ?? null, $vehicle)) {
                session()->flash('tire_size_warning', $warning);
            }
        }

        foreach ($tires as $tire) {
            $maintenanceRecord->items()->create([
                'itemable_id' => $tire->id,
                'itemable_type' => Tire::class,
                'completed' => false,
            ]);
        }
    }
}
