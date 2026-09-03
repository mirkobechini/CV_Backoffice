<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeadlineBusinessLogicTest extends TestCase
{
    /*
    calculateMinisterialDueDateForVehicle()
    calculateOxygenDueDateForVehicle()
    eventuale getAutomaticStatusAttribute()
    eventuale syncStatusFromRules()
     */
    use RefreshDatabase;

    public function test_calculate_ministerial_due_date_for_first_inspection(): void
    {
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);

        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create([
            'name' => 'Ducato',
            'brand_id' => $brand->id,
        ]);

        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
        ]);

        $expectedDueDate = $vehicle->immatricolation_date->copy()->addMonths($vehicle->vehicleType->first_inspection_months)->toDateString();

        $this->assertEquals($expectedDueDate, Deadline::calculateMinisterialDueDateForVehicle($vehicle)->toDateString());
    }

    public function test_calculate_ministerial_due_date_for_regular_inspection(): void
    {
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);

        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create([
            'name' => 'Ducato',
            'brand_id' => $brand->id,
        ]);

        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
        ]);

        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'status' => Deadline::STATUS_RENEWED,
            'due_date' => '2025-01-01',
        ]);

        $expectedDueDate = $vehicle->immatricolation_date->copy()->addMonths($vehicle->vehicleType->first_inspection_months)->addMonths($vehicle->vehicleType->regular_inspection_months)->toDateString();

        $this->assertEquals($expectedDueDate, Deadline::calculateMinisterialDueDateForVehicle($vehicle)->toDateString());
    }

    public function test_calculate_oxygen_due_date_for_vehicle_that_requires_oxygen_check(): void
    {
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);

        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create([
            'name' => 'Ducato',
            'brand_id' => $brand->id,
        ]);

        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
        ]);

        $expectedDueDate = $vehicle->immatricolation_date->copy()->addMonths(Deadline::OXYGEN_CHECK_INTERVAL_MONTHS)->toDateString();

        $this->assertEquals($expectedDueDate, Deadline::calculateOxygenDueDateForVehicle($vehicle)->toDateString());
    }

    public function test_calculate_oxygen_due_date_returns_null_when_vehicle_type_does_not_require_it(): void
    {
        $vehicleType = VehicleType::create([
            'name' => 'Auto',
            'needs_oxygen_check' => false,
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create([
            'name' => 'Panda',
            'brand_id' => $brand->id,
        ]);

        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'benzina',
            'immatricolation_date' => '2024-01-01',
        ]);

        $this->assertNull(Deadline::calculateOxygenDueDateForVehicle($vehicle));
    }

    public function test_deadline_is_expired_when_due_date_is_in_the_past()
    {
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);

        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create([
            'name' => 'Ducato',
            'brand_id' => $brand->id,
        ]);

        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
        ]);

        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'status' => Deadline::STATUS_PENDING,
            'due_date' => now()->subDays(1)->toDateString(),
        ]);

        $this->assertEquals(Deadline::STATUS_EXPIRED, $deadline->automatic_status);
    }

    public function test_deadline_is_valid_when_due_date_is_in_the_future()
    {
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);

        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create([
            'name' => 'Ducato',
            'brand_id' => $brand->id,
        ]);

        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
        ]);

        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'status' => Deadline::STATUS_PENDING,
            'due_date' => now()->addMonths(4)->toDateString(),
        ]);

        $this->assertEquals(Deadline::STATUS_VALID, $deadline->automatic_status);
    }

    public function test_deadline_is_renewed_when_marked_as_renewed()
    {
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);

        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create([
            'name' => 'Ducato',
            'brand_id' => $brand->id,
        ]);

        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
        ]);

        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'status' => Deadline::STATUS_PENDING,
            'is_renewed' => true,
            'due_date' => now()->subDays(10)->toDateString(),
        ]);

        $this->assertEquals(Deadline::STATUS_RENEWED, $deadline->automatic_status);
    }

    public function test_deadline_is_pending_when_due_date_is_within_warning_window()
    {
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);

        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create([
            'name' => 'Ducato',
            'brand_id' => $brand->id,
        ]);

        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
        ]);

        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'status' => Deadline::STATUS_PENDING,
            'due_date' => now()->addMonths(1)->toDateString(),
        ]);

        $this->assertEquals(Deadline::STATUS_PENDING, $deadline->automatic_status);
    }

    public function test_sync_statuses_from_rules_updates_multiple_deadlines_in_bulk()
    {
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);

        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create([
            'name' => 'Ducato',
            'brand_id' => $brand->id,
        ]);

        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
        ]);

        // Scadenza scaduta (ieri) -> deve diventare expired
        $expired = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'status' => Deadline::STATUS_PENDING,
            'due_date' => now()->subDays(1)->toDateString(),
        ]);

        // Scadenza valida (tra 4 mesi) -> deve diventare valid
        $valid = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_OXYGEN,
            'status' => Deadline::STATUS_PENDING,
            'due_date' => now()->addMonths(4)->toDateString(),
        ]);

        // Scadenza rinnovata manualmente -> deve restare renewed
        $renewed = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'status' => Deadline::STATUS_RENEWED,
            'is_renewed' => true,
            'due_date' => now()->subDays(10)->toDateString(),
        ]);

        $collection = Deadline::query()->whereIn('id', [$expired->id, $valid->id, $renewed->id])->get();
        Deadline::syncStatusesFromRules($collection);

        $this->assertEquals(Deadline::STATUS_EXPIRED, $expired->fresh()->status);
        $this->assertEquals(Deadline::STATUS_VALID, $valid->fresh()->status);
        $this->assertEquals(Deadline::STATUS_RENEWED, $renewed->fresh()->status);
    }
}
