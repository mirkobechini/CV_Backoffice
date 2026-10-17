<?php

namespace Tests\Feature\Crud;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Misure pneumatici consigliate (codice) e combinazioni ammesse per asse —
 * spezzato da VehicleCrudTest per restare sotto ~500 righe.
 */
class VehicleTireSizeTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    private function createVehicleDependencies(): array
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        return compact('brand', 'carModel', 'vehicleType');
    }

    private function createVehicle(): array
    {
        $dependencies = $this->createVehicleDependencies();
        $brand = $dependencies['brand'];
        $carModel = $dependencies['carModel'];
        $vehicleType = $dependencies['vehicleType'];

        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ]);

        return compact('brand', 'carModel', 'vehicleType', 'vehicle');
    }

    public function test_show_page_displays_allowed_tire_sizes_and_combinations(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle()['vehicle'];
        $vehicle->update([
            'allowed_tire_sizes' => [
                ['size' => '225/75R16C', 'code' => 'A1'],
                ['size' => '205/55R16', 'code' => 'A2'],
            ],
            'allowed_tire_size_combinations' => [
                ['front_code' => 'A1', 'rear_code' => 'A2'],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('admin.vehicles.show', $vehicle));

        $response->assertOk();
        $response->assertSee('A1: 225/75R16C');
        $response->assertSee('A1-A2');
    }

    public function test_vehicle_store_saves_allowed_tire_sizes_with_code(): void
    {
        $user = $this->createUser();
        $data = $this->createVehicleDependencies();

        $response = $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $data['vehicleType']->id,
            'internal_code' => '1234',
            'brand_id' => $data['brand']->id,
            'car_model_id' => $data['carModel']->id,
            'immatricolation_date' => '2024-01-01',
            'has_warranty_extension' => 0,
            'allowed_tire_sizes' => [
                ['size' => '225/75R16C', 'code' => 'A1'],
                ['size' => '205/55R16', 'code' => 'A2'],
            ],
        ]);

        $vehicle = Vehicle::first();
        $response->assertRedirect(route('admin.vehicles.show', $vehicle));
        $this->assertSame([
            ['size' => '225/75R16C', 'code' => 'A1'],
            ['size' => '205/55R16', 'code' => 'A2'],
        ], $vehicle->fresh()->allowed_tire_sizes);
    }

    public function test_vehicle_store_discards_tire_size_rows_without_both_size_and_code(): void
    {
        // Le righe rimosse/vuote del repeater inviano comunque un campo
        // nascosto vuoto: non devono fallire la validazione né finire nel
        // dato salvato. Misura e codice sono obbligatori insieme: una
        // misura senza codice non è referenziabile da una combinazione.
        $user = $this->createUser();
        $data = $this->createVehicleDependencies();

        $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $data['vehicleType']->id,
            'internal_code' => '1234',
            'brand_id' => $data['brand']->id,
            'car_model_id' => $data['carModel']->id,
            'immatricolation_date' => '2024-01-01',
            'has_warranty_extension' => 0,
            'allowed_tire_sizes' => [
                ['size' => '', 'code' => 'A1'],
                ['size' => '225/75R16C', 'code' => ''],
                ['size' => '205/55R16', 'code' => 'A2'],
            ],
        ])->assertRedirect();

        $vehicle = Vehicle::first();
        $this->assertSame([['size' => '205/55R16', 'code' => 'A2']], $vehicle->fresh()->allowed_tire_sizes);
    }

    public function test_vehicle_store_saves_allowed_tire_size_combinations(): void
    {
        $user = $this->createUser();
        $data = $this->createVehicleDependencies();

        $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $data['vehicleType']->id,
            'internal_code' => '1234',
            'brand_id' => $data['brand']->id,
            'car_model_id' => $data['carModel']->id,
            'immatricolation_date' => '2024-01-01',
            'has_warranty_extension' => 0,
            'allowed_tire_sizes' => [
                ['size' => '225/75R16C', 'code' => 'A1'],
                ['size' => '205/55R16', 'code' => 'A2'],
            ],
            'allowed_tire_size_combinations' => [
                ['front_code' => 'A1', 'rear_code' => 'A2'],
                ['front_code' => 'A2', 'rear_code' => 'A2'],
            ],
        ])->assertRedirect();

        $vehicle = Vehicle::first();
        $this->assertSame([
            ['front_code' => 'A1', 'rear_code' => 'A2'],
            ['front_code' => 'A2', 'rear_code' => 'A2'],
        ], $vehicle->fresh()->allowed_tire_size_combinations);
    }

    public function test_vehicle_store_rejects_combination_referencing_unknown_code(): void
    {
        $user = $this->createUser();
        $data = $this->createVehicleDependencies();

        $response = $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $data['vehicleType']->id,
            'internal_code' => '1234',
            'brand_id' => $data['brand']->id,
            'car_model_id' => $data['carModel']->id,
            'immatricolation_date' => '2024-01-01',
            'has_warranty_extension' => 0,
            'allowed_tire_sizes' => [
                ['size' => '225/75R16C', 'code' => 'A1'],
            ],
            'allowed_tire_size_combinations' => [
                ['front_code' => 'A1', 'rear_code' => 'A3'],
            ],
        ]);

        $response->assertSessionHasErrors(['allowed_tire_size_combinations.0.rear_code']);
        $this->assertDatabaseMissing('vehicles', ['license_plate' => 'AB123CD']);
    }
}
