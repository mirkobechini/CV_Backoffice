<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleBusinessLogicTest extends TestCase
{
    use RefreshDatabase;


    public function test_vehicle_has_all_required_equipment_when_requirements_are_met(): void
    {
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        $extinguisherType = EquipmentType::create([
            'name' => 'Estintore',
            'first_inspection_months' => 6,
            'regular_inspection_months' => 6,
        ]);

        $stretcherType = EquipmentType::create([
            'name' => 'Barella',
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);

        $vehicleType->equipmentTypes()->attach($extinguisherType->id, ['required_quantity' => 1]);
        $vehicleType->equipmentTypes()->attach($stretcherType->id, ['required_quantity' => 1]);

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

        Equipment::create([
            'vehicle_id' => $vehicle->id,
            'equipment_type_id' => $extinguisherType->id,
            'name' => 'Estintore 1',
        ]);

        Equipment::create([
            'vehicle_id' => $vehicle->id,
            'equipment_type_id' => $stretcherType->id,
            'name' => 'Barella 1',
        ]);

        $this->assertTrue($vehicle->fresh()->hasAllRequiredEquipment()); //Fresh per ricaricare la relazione equipmentTypes dopo aver aggiunto gli equipaggiamenti
    }

    public function test_vehicle_does_not_have_all_required_equipment_when_one_is_missing(): void
    {
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        $extinguisherType = EquipmentType::create([
            'name' => 'Estintore',
            'first_inspection_months' => 6,
            'regular_inspection_months' => 6,
        ]);

        $stretcherType = EquipmentType::create([
            'name' => 'Barella',
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);

        $vehicleType->equipmentTypes()->attach($extinguisherType->id, ['required_quantity' => 1]);
        $vehicleType->equipmentTypes()->attach($stretcherType->id, ['required_quantity' => 1]);

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

        Equipment::create([
            'vehicle_id' => $vehicle->id,
            'equipment_type_id' => $extinguisherType->id,
            'name' => 'Estintore 1',
        ]);

        $this->assertFalse($vehicle->fresh()->hasAllRequiredEquipment()); //Fresh per ricaricare la relazione equipmentTypes dopo aver aggiunto gli equipaggiamenti
    }

    public function test_vehicle_missing_required_equipment_returns_missing_items(): void
    {
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        $extinguisherType = EquipmentType::create([
            'name' => 'Estintore',
            'first_inspection_months' => 6,
            'regular_inspection_months' => 6,
        ]);

        $stretcherType = EquipmentType::create([
            'name' => 'Barella',
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);

        $vehicleType->equipmentTypes()->attach($extinguisherType->id, ['required_quantity' => 1]);
        $vehicleType->equipmentTypes()->attach($stretcherType->id, ['required_quantity' => 1]);

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

        Equipment::create([
            'vehicle_id' => $vehicle->id,
            'equipment_type_id' => $extinguisherType->id,
            'name' => 'Estintore 1',
        ]);

        $missing = $vehicle->fresh()->missingRequiredEquipment();
        $this->assertCount(1, $missing);
        $this->assertNotEmpty($missing);
        $this->assertTrue($missing->contains('name', 'Barella'));
        $this->assertFalse($vehicle->fresh()->hasAllRequiredEquipment()); //Fresh per ricaricare la relazione equipmentTypes dopo aver aggiunto gli equipaggiamenti
    }

    public function test_vehicle_missing_required_equipment_considers_required_quantity(): void
    {
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        $extinguisherType = EquipmentType::create([
            'name' => 'Estintore',
            'first_inspection_months' => 6,
            'regular_inspection_months' => 6,
        ]);
        $vehicleType->equipmentTypes()->attach($extinguisherType->id, ['required_quantity' => 2]);

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

        Equipment::create([
            'vehicle_id' => $vehicle->id,
            'equipment_type_id' => $extinguisherType->id,
            'name' => 'Estintore 1',
        ]);

        $missing = $vehicle->fresh()->missingRequiredEquipment();
        $this->assertFalse($vehicle->fresh()->hasAllRequiredEquipment()); //Fresh per ricaricare la relazione equipmentTypes dopo aver aggiunto gli equipaggiamenti
        $this->assertTrue($missing->contains('name', 'Estintore'));
    }

    public function test_vehicle_with_no_vehicle_type_is_handled_safely(): void
    {
        $extinguisherType = EquipmentType::create([
            'name' => 'Estintore',
            'first_inspection_months' => 6,
            'regular_inspection_months' => 6,
        ]);

        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create([
            'name' => 'Ducato',
            'brand_id' => $brand->id,
        ]);

        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
        ]);

        Equipment::create([
            'vehicle_id' => $vehicle->id,
            'equipment_type_id' => $extinguisherType->id,
            'name' => 'Estintore 1',
        ]);

        $missing = $vehicle->fresh()->missingRequiredEquipment();
        $this->assertTrue($missing->isEmpty());
        $this->assertTrue($vehicle->hasAllRequiredEquipment());
    }
}
