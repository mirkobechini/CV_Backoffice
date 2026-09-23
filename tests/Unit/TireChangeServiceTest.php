<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Tire;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\TireChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TireChangeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TireChangeService
    {
        return new TireChangeService();
    }

    private function vehicle(): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);

        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => '2024-01-01',
        ]);
    }

    private function tire(Vehicle $vehicle, array $overrides = []): Tire
    {
        return Tire::create(array_merge([
            'vehicle_id' => $vehicle->id,
            'season' => 'winter',
            'position' => Tire::POSITION_FRONT_LEFT,
            'status' => 'stored',
        ], $overrides));
    }

    public function test_mounting_a_tire_unmounts_whatever_was_at_the_same_position(): void
    {
        $vehicle = $this->vehicle();
        $old = $this->tire($vehicle, ['status' => 'mounted']);
        $new = $this->tire($vehicle, ['season' => 'summer']);

        $this->service()->recordChange($new, Carbon::parse('2026-04-15'), 50000, 'stored');

        $this->assertSame('mounted', $new->fresh()->status);
        $this->assertSame('stored', $old->fresh()->status);
    }

    public function test_mounting_a_tire_does_not_touch_other_positions(): void
    {
        $vehicle = $this->vehicle();
        $frontLeft = $this->tire($vehicle, ['position' => Tire::POSITION_FRONT_LEFT, 'status' => 'mounted']);
        $frontRight = $this->tire($vehicle, ['position' => Tire::POSITION_FRONT_RIGHT, 'status' => 'mounted']);
        $rearLeft = $this->tire($vehicle, ['position' => Tire::POSITION_REAR_LEFT, 'status' => 'mounted']);
        $rearRight = $this->tire($vehicle, ['position' => Tire::POSITION_REAR_RIGHT, 'status' => 'mounted']);
        $newFrontLeft = $this->tire($vehicle, ['position' => Tire::POSITION_FRONT_LEFT, 'season' => 'summer', 'status' => 'stored']);

        $this->service()->recordChange($newFrontLeft, Carbon::parse('2026-04-15'), null, 'retired');

        $this->assertSame('mounted', $newFrontLeft->fresh()->status);
        $this->assertSame('retired', $frontLeft->fresh()->status);
        $this->assertSame('mounted', $frontRight->fresh()->status);
        $this->assertSame('mounted', $rearLeft->fresh()->status);
        $this->assertSame('mounted', $rearRight->fresh()->status);
    }

    public function test_mounting_a_tire_with_nothing_previously_mounted_at_that_position(): void
    {
        $vehicle = $this->vehicle();
        $new = $this->tire($vehicle, ['status' => 'stored']);

        $change = $this->service()->recordChange($new, Carbon::parse('2026-04-15'), null, 'stored');

        $this->assertSame('mounted', $new->fresh()->status);
        $this->assertNull($change->previous_tire_id);
    }

    public function test_records_tire_change_history_with_previous_tire(): void
    {
        $vehicle = $this->vehicle();
        $old = $this->tire($vehicle, ['status' => 'mounted']);
        $new = $this->tire($vehicle, ['season' => 'summer']);

        $change = $this->service()->recordChange($new, Carbon::parse('2026-04-15'), 12345, 'stored', 'note di test');

        $this->assertSame($vehicle->id, $change->vehicle_id);
        $this->assertSame($new->id, $change->tire_id);
        $this->assertSame($old->id, $change->previous_tire_id);
        $this->assertSame(12345, $change->mileage_at_change);
        $this->assertSame('note di test', $change->notes);
    }
}
