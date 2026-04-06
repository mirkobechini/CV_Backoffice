<?php

namespace Tests\Feature\Validation;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeadlineValidationTest extends TestCase
{
    
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->create();
    }

    private function createVehicle(): Vehicle
    {
        $brand = Brand::create([
            'name' => 'Fiat',
        ]);

        $carModel = CarModel::create([
            'name' => 'Ducato',
            'brand_id' => $brand->id,
        ]);

        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
        ]);
    }

    public function test_deadline_store_fails_when_type_is_missing(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $actualDeadlineCount = Deadline::count();

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'status' => 'renewed',
            'due_date' => "2025-01",
        ]);
        $response->assertSessionHasErrors(['type']);
        $this->assertDatabaseCount('deadlines', $actualDeadlineCount);
    }
    public function test_deadline_store_fails_when_vehicle_id_is_missing(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'type' => Deadline::TYPE_MINISTERIAL,
            'status' => 'renewed',
            'due_date' => "2025-01",
        ]);
        $response->assertSessionHasErrors(['vehicle_id']);
        $this->assertDatabaseEmpty('deadlines');
    }

    public function test_deadline_store_fails_when_due_date_has_invalid_format(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $actualDeadlineCount = Deadline::count();

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'status' => 'renewed',
            'due_date' => "02-2025-01",
        ]);
        $response->assertSessionHasErrors(['due_date']);
        
        $this->assertDatabaseCount('deadlines', $actualDeadlineCount);
    }

    public function test_deadline_store_requires_due_date_for_non_automatic_types(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $actualDeadlineCount = Deadline::count();

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => "Assicurazione",
            'status' => 'renewed',
        ]);
        $response->assertSessionHasErrors(['due_date']);
        $this->assertDatabaseCount('deadlines', $actualDeadlineCount);
    }
}
