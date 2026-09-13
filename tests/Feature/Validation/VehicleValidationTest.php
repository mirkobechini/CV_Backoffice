<?php

namespace Tests\Feature\Validation;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleValidationTest extends TestCase
{

    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    /**
     * Stesso gruppo creato dallo stato "admin" di UserFactory::withRole():
     * i veicoli devono appartenervi per essere visibili/accessibili
     * all'utente di questi test (le route sono scoperte per gruppo).
     */
    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    public function test_vehicle_store_fails_when_car_model_does_not_belong_to_brand(): void
    {
        $user = $this->createUser();

        $brandA = Brand::create(['name' => 'Fiat']);
        $brandB = Brand::create(['name' => 'Ford']);
        $carModel = CarModel::create([
            'name' => 'Ducato',
            'brand_id' => $brandA->id,
        ]);
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        $response = $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brandB->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'has_warranty_extension' => 0,
        ]);

        $response->assertSessionHasErrors(['car_model_id']);
        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_vehicle_update_fails_when_car_model_does_not_belong_to_brand(): void
    {
        $user = $this->createUser();

        $brandA = Brand::create(['name' => 'Fiat']);
        $brandB = Brand::create(['name' => 'Ford']);
        $carModel = CarModel::create([
            'name' => 'Ducato',
            'brand_id' => $brandA->id,
        ]);
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brandA->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ]);

        $response = $this->actingAs($user)->put(route('admin.vehicles.update', $vehicle), [
            'brand_id' => $brandB->id,
            'car_model_id' => $carModel->id,
        ]);

        $response->assertSessionHasErrors(['car_model_id']);

        $this->assertDatabaseHas('vehicles', [
            'brand_id' => $brandA->id,
            'car_model_id' => $carModel->id,
        ]);
    }
}
