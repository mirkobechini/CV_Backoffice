<?php

namespace Tests\Feature\Crud;

//Per via della middleware auth
use App\Models\User;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;


use Tests\TestCase;

class VehicleCrudTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->create();
    }

    private function createVehicleDependencies(): array
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
        ]);

        return compact('brand', 'carModel', 'vehicleType', 'vehicle');
    }

    //RAGGIUNGIBILITA' ROTTE

    public function test_vehicle_index_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.vehicles.index'));

        $response->assertStatus(200);
    }


    public function test_vehicle_create_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user

        $response = $this->actingAs($user)->get(route('admin.vehicles.create'));

        $response->assertStatus(200);
    }



    public function test_vehicle_show_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $vehicle = $this->createVehicle()['vehicle'];

        $response = $this->actingAs($user)->get(route('admin.vehicles.show', $vehicle));

        $response->assertStatus(200);
    }

    public function test_vehicle_edit_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $vehicle = $this->createVehicle()['vehicle'];

        $response = $this->actingAs($user)->get(route('admin.vehicles.edit', $vehicle));

        $response->assertStatus(200);
    }


    public function test_vehicle_can_be_stored(): void
    {
        $user = $this->createUser();    //fake user
        $data = $this->createVehicleDependencies();
        $vehicleType = $data['vehicleType'];
        $brand = $data['brand'];
        $carModel = $data['carModel'];

        $response = $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'has_warranty_extension' => 0,
        ]);

        $vehicle = Vehicle::first();

        $response->assertRedirect(route('admin.vehicles.show', $vehicle));

        $this->assertDatabaseHas('vehicles', [
            'license_plate' => 'AB123CD',
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
        ]);
    }


    public function test_vehicle_can_be_updated(): void
    {
        $user = $this->createUser();
        $data = $this->createVehicle();

        $vehicle = $data['vehicle'];
        $brand = $data['brand'];
        $carModel = $data['carModel'];
        $vehicleType = $data['vehicleType'];

        $response = $this->actingAs($user)->put(route('admin.vehicles.update', $vehicle), [
            'license_plate' => 'ZZ999YY',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '5678',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'has_warranty_extension' => 0,
        ]);

        $response->assertRedirect(route('admin.vehicles.show', $vehicle));

        $this->assertDatabaseHas('vehicles', [
            'license_plate' => 'ZZ999YY',
            'internal_code' => '5678',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
        ]);
    }

    public function test_vehicle_can_be_deleted(): void
    {
        $user = $this->createUser();

        $vehicle = $this->createVehicle()['vehicle'];

        $response = $this->actingAs($user)->delete(route('admin.vehicles.destroy', $vehicle));

        $response->assertRedirect(route('admin.vehicles.index'));
        $this->assertDatabaseMissing('vehicles', [
            'id' => $vehicle->id,
        ]);
    }

    //VINCOLI UNIVOCITA'

    public function test_vehicle_cannot_be_stored_with_duplicate_license_plate()
    {
        $user = $this->createUser();    //fake user
        $data = $this->createVehicle();

        $vehicle = $data['vehicle'];
        $brand = $data['brand'];
        $carModel = $data['carModel'];
        $vehicleType = $data['vehicleType'];

        //verifica collegamento torna al form
        $response = $this->from(route('admin.vehicles.create'))
            ->actingAs($user)->post(route('admin.vehicles.store'), [
                'license_plate' => $vehicle->license_plate,
                'vehicle_type_id' => $vehicleType->id,
                'internal_code' => '1234',
                'brand_id' => $brand->id,
                'car_model_id' => $carModel->id,
                'fuel_type' => 'diesel',
                'immatricolation_date' => '2024-01-01',
                'has_warranty_extension' => 0,
            ]);

        //verifica univocità targa
        $response->assertSessionHasErrors(['license_plate']);
        $this->assertDatabaseCount('vehicles', 1); //verifica che non sia stato creato un secondo veicolo con la solita targa

    }


    public function test_vehicle_cannot_be_updated_with_duplicate_license_plate()
    {
        $user = $this->createUser();    //fake user
        $data = $this->createVehicle();

        $vehicleBase = $data['vehicle'];
        $brand = $data['brand'];
        $carModel = $data['carModel'];
        $vehicleType = $data['vehicleType'];

        $vehicle = Vehicle::create([
            'license_plate' => 'ZZ999YY',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
        ]);

        //verifica collegamento torna al form
        $response = $this->from(route('admin.vehicles.edit', $vehicle))->actingAs($user)->put(route('admin.vehicles.update', $vehicle), [
                'license_plate' => $vehicleBase->license_plate,
                'vehicle_type_id' => $vehicleType->id,
                'internal_code' => '1234',
                'brand_id' => $brand->id,
                'car_model_id' => $carModel->id,
                'fuel_type' => 'diesel',
                'immatricolation_date' => '2024-01-01',
                'has_warranty_extension' => 0,
            ]);

        //verifica univocità targa
        $response->assertSessionHasErrors(['license_plate']);
        $this->assertDatabaseHas('vehicles', [
            'id'=>$vehicle->id,
            'license_plate' => $vehicle->license_plate
        ]); //verifica che non sia stato aggiornato il veicolo con la targa gia esistente

    }
}
