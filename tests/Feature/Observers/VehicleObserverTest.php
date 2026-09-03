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

    private function createVehicle(VehicleType $vehicleType, bool $hasTimingBelt = false): Vehicle
    {
        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => null,
            'car_model_id' => null,
            'fuel_type' => 'diesel',
            'immatricolation_date' => today()->toDateString(),
            'has_timing_belt' => $hasTimingBelt,
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
        // ministeriale + ossigeno + tagliando
        $this->assertDatabaseCount('deadlines', 3);
    }

    public function test_vehicle_creation_generates_first_tagliando_deadline(): void
    {
        $vehicleType = $this->createVehicleType();
        $vehicle = $this->createVehicle($vehicleType);

        $this->assertDatabaseHas('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'interval_km' => 25000,
            'last_mileage' => 0,
        ]);

        // La scadenza tagliando ha anche una data (1 anno dall'immatricolazione)
        $deadline = Deadline::where('vehicle_id', $vehicle->id)
            ->where('type', Deadline::TYPE_TAGLIANDO)
            ->first();
        $this->assertNotNull($deadline->due_date);
        $this->assertEquals(
            $vehicle->immatricolation_date->copy()->addMonthsNoOverflow(Deadline::TAGLIANDO_INTERVAL_MONTHS)->toDateString(),
            $deadline->due_date->toDateString()
        );
    }

    public function test_vehicle_creation_generates_first_tagliando_with_custom_km(): void
    {
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
            'first_tagliando_km' => 30000,
        ]);
        $vehicle = $this->createVehicle($vehicleType);

        $this->assertDatabaseHas('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'interval_km' => 30000,
        ]);
    }

    public function test_vehicle_creation_generates_timing_belt_deadline_when_has_timing_belt(): void
    {
        $vehicleType = $this->createVehicleType();
        $vehicle = $this->createVehicle($vehicleType, true);

        $this->assertDatabaseHas('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
            'interval_km' => Deadline::TIMING_BELT_INTERVAL_KM,
            'interval_days' => Deadline::TIMING_BELT_INTERVAL_DAYS,
        ]);
    }

    public function test_vehicle_creation_does_not_generate_timing_belt_deadline_when_not_has_timing_belt(): void
    {
        $vehicleType = $this->createVehicleType();
        $vehicle = $this->createVehicle($vehicleType, false);

        $this->assertDatabaseMissing('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
        ]);
    }
}
