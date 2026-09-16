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
            'axle' => 'full',
            'quantity' => 4,
            'status' => 'stored',
        ], $overrides));
    }

    public function test_full_set_replaces_a_previously_mounted_full_set(): void
    {
        $vehicle = $this->vehicle();
        $oldFull = $this->tire($vehicle, ['status' => 'mounted']);
        $newFull = $this->tire($vehicle, ['season' => 'summer']);

        $this->service()->recordChange($newFull, Carbon::parse('2026-04-15'), 50000, 'stored');

        $this->assertSame('mounted', $newFull->fresh()->status);
        $this->assertSame('stored', $oldFull->fresh()->status);
    }

    public function test_full_set_replaces_both_a_mounted_front_and_rear_set(): void
    {
        $vehicle = $this->vehicle();
        $front = $this->tire($vehicle, ['axle' => 'front', 'quantity' => 2, 'status' => 'mounted']);
        $rear = $this->tire($vehicle, ['axle' => 'rear', 'quantity' => 2, 'status' => 'mounted']);
        $newFull = $this->tire($vehicle, ['season' => 'summer']);

        $this->service()->recordChange($newFull, Carbon::parse('2026-04-15'), null, 'retired');

        $this->assertSame('mounted', $newFull->fresh()->status);
        $this->assertSame('retired', $front->fresh()->status);
        $this->assertSame('retired', $rear->fresh()->status);
    }

    public function test_replacing_only_front_axle_leaves_rear_of_full_set_mounted_via_split(): void
    {
        $vehicle = $this->vehicle();
        $oldFull = $this->tire($vehicle, ['status' => 'mounted', 'brand' => 'Michelin']);
        $newFront = $this->tire($vehicle, ['axle' => 'front', 'quantity' => 2, 'status' => 'stored', 'brand' => 'Pirelli']);

        $this->service()->recordChange($newFront, Carbon::parse('2026-04-15'), null, 'stored');

        $this->assertSame('mounted', $newFront->fresh()->status);

        // Il set originale non è più una gomma "attiva": è stato superato
        // dallo split, non dismesso per scelta dell'utente.
        $this->assertSame('retired', $oldFull->fresh()->status);

        // Le posteriori del vecchio set restano montate su un nuovo record
        // "rear" con gli stessi dati (stesso brand del set originale).
        $splitRear = Tire::where('vehicle_id', $vehicle->id)
            ->where('axle', 'rear')
            ->where('status', 'mounted')
            ->first();

        $this->assertNotNull($splitRear);
        $this->assertSame('Michelin', $splitRear->brand);
        $this->assertSame(2, $splitRear->quantity);
    }

    public function test_replacing_front_axle_again_does_not_touch_separately_tracked_rear(): void
    {
        $vehicle = $this->vehicle();
        $rear = $this->tire($vehicle, ['axle' => 'rear', 'quantity' => 2, 'status' => 'mounted']);
        $oldFront = $this->tire($vehicle, ['axle' => 'front', 'quantity' => 2, 'status' => 'mounted']);
        $newFront = $this->tire($vehicle, ['axle' => 'front', 'quantity' => 2, 'status' => 'stored']);

        $this->service()->recordChange($newFront, Carbon::parse('2026-06-01'), null, 'retired');

        $this->assertSame('mounted', $newFront->fresh()->status);
        $this->assertSame('retired', $oldFront->fresh()->status);
        $this->assertSame('mounted', $rear->fresh()->status);
    }

    public function test_records_tire_change_history_with_previous_tire(): void
    {
        $vehicle = $this->vehicle();
        $oldFull = $this->tire($vehicle, ['status' => 'mounted']);
        $newFull = $this->tire($vehicle, ['season' => 'summer']);

        $change = $this->service()->recordChange($newFull, Carbon::parse('2026-04-15'), 12345, 'stored', 'note di test');

        $this->assertSame($vehicle->id, $change->vehicle_id);
        $this->assertSame($newFull->id, $change->tire_id);
        $this->assertSame($oldFull->id, $change->previous_tire_id);
        $this->assertSame(12345, $change->mileage_at_change);
        $this->assertSame('note di test', $change->notes);
    }
}
