<?php

namespace Tests\Feature\Observers;

use App\Models\Deadline;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleObserverTest extends TestCase
{

    use RefreshDatabase;
    
    //Helper methods
    private function createVehicleType(bool $needsOxygenCheck = true): VehicleType
    {
        return VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => $needsOxygenCheck,
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);
    }

    private function createVehicle(VehicleType $vehicleType): Vehicle
    {
        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => null,
            'car_model_id' => null,
            'fuel_type' => 'diesel',
            'immatricolation_date' => today()->toDateString(),
        ]);
    }

    //Test methods
    public function test_vehicle_creation_generates_ministerial_deadline(): void
    {
        $vehicleType = $this->createVehicleType();
        $vehicle = $this->createVehicle($vehicleType);
        $this->assertDatabaseHas('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
        ]);
    }

    public function test_vehicle_creation_generates_oxygen_deadline_when_required(): void
    {
        $vehicleType = $this->createVehicleType(true);
        $vehicle = $this->createVehicle($vehicleType);
        $this->assertDatabaseHas('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_OXYGEN,
        ]);
    }

    public function test_vehicle_creation_does_not_generate_oxygen_deadline_when_not_required(): void
    {
        $vehicleType = $this->createVehicleType(false);
        $vehicle = $this->createVehicle($vehicleType);
        $this->assertDatabaseMissing('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_OXYGEN,
        ]);
    }

    public function test_vehicle_observer_does_not_create_duplicate_deadlines(): void
    {
        $vehicleType = $this->createVehicleType();
        $vehicle = $this->createVehicle($vehicleType);
        $this->assertDatabaseCount('deadlines', 2);
    }
}
