<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleShowMissingEquipmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    /**
     * Stesso gruppo creato dallo stato "admin" di UserFactory::withRole():
     * il veicolo deve appartenervi per essere visibile/accessibile
     * all'utente di questi test (le route sono scoperte per gruppo).
     */
    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    private function createVehicle(VehicleType $vehicleType): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);

        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'vehicle_type_id' => $vehicleType->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ]);
    }

    public function test_show_page_lists_missing_equipment_with_quick_add_link(): void
    {
        $user = $this->admin();
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);
        $extinguisherType = EquipmentType::create([
            'name' => 'Estintore',
            'category' => EquipmentType::CATEGORY_FIRE_EXTINGUISHER,
        ]);
        $vehicleType->equipmentTypes()->attach($extinguisherType->id, ['required_quantity' => 2]);

        $vehicle = $this->createVehicle($vehicleType);
        Equipment::create([
            'name' => 'Estintore cabina',
            'equipment_type_id' => $extinguisherType->id,
            'vehicle_id' => $vehicle->id,
            'serial_number' => 'EX-1',
        ]);

        $response = $this->actingAs($user)->get(route('admin.vehicles.show', $vehicle));

        $response->assertOk();
        $response->assertSee('Estintore', false);
        $response->assertSee(__('Presenti :available di :required richieste', ['available' => 1, 'required' => 2]));
        $response->assertSee(route('admin.equipments.create'), false);
        $response->assertSee('equipment_type_id=' . $extinguisherType->id, false);
    }

    public function test_show_page_does_not_list_missing_equipment_when_requirements_are_met(): void
    {
        $user = $this->admin();
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);
        $extinguisherType = EquipmentType::create([
            'name' => 'Estintore',
            'category' => EquipmentType::CATEGORY_FIRE_EXTINGUISHER,
        ]);
        $vehicleType->equipmentTypes()->attach($extinguisherType->id, ['required_quantity' => 1]);

        $vehicle = $this->createVehicle($vehicleType);
        Equipment::create([
            'name' => 'Estintore cabina',
            'equipment_type_id' => $extinguisherType->id,
            'vehicle_id' => $vehicle->id,
            'serial_number' => 'EX-1',
        ]);

        $response = $this->actingAs($user)->get(route('admin.vehicles.show', $vehicle));

        $response->assertOk();
        $response->assertDontSee('Mancante');
    }
}
