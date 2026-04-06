<?php

namespace Tests\Feature\Crud;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\MileageLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MileageLogCrudTest extends TestCase
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

    private function createMileageLog(): array
    {
        $vehicle = $this->createVehicle();

        $mileageLog = MileageLog::create([
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-01-25',
            'mileage' => '1234'
        ]);

        return compact('vehicle', 'mileageLog');
    }

    public function test_mileage_log_index_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.mileage-logs.index'));

        $response->assertStatus(200);
    }


    public function test_mileage_log_create_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user

        $response = $this->actingAs($user)->get(route('admin.mileage-logs.create'));

        $response->assertStatus(200);
    }



    public function test_mileage_log_show_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $mileageLog = $this->createMileageLog()['mileageLog'];

        $response = $this->actingAs($user)->get(route('admin.mileage-logs.show', $mileageLog));

        $response->assertStatus(200);
    }

    public function test_mileage_log_edit_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $mileageLog = $this->createMileageLog()['mileageLog'];

        $response = $this->actingAs($user)->get(route('admin.mileage-logs.edit', $mileageLog));

        $response->assertStatus(200);
    }


    public function test_mileage_log_can_be_stored(): void
    {
        $user = $this->createUser();    //fake user
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.mileage-logs.store'), [
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-01-25',
            'mileage' => '1234'
        ]);

        $mileageLog = MileageLog::first();

        $response->assertRedirect(route('admin.mileage-logs.show', $mileageLog));
        $this->assertDatabaseHas('mileage_logs', [
            'id' => $mileageLog->id,
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-01-25 00:00:00',
            'mileage' => 1234,
        ]);
    }


    public function test_mileage_log_can_be_updated(): void
    {
        $user = $this->createUser();
        $data = $this->createMileageLog();
        $vehicle = $data['vehicle'];
        $mileageLog = $data['mileageLog'];

        $response = $this->actingAs($user)->put(route('admin.mileage-logs.update', $mileageLog), [
            'vehicle_id' => $vehicle->id,
            'log_date' => '2024-01-25',
            'mileage' => '5678'
        ]);

        $response->assertRedirect(route('admin.mileage-logs.show', $mileageLog));
        $this->assertDatabaseHas('mileage_logs', [
            'id' => $mileageLog->id,
            'vehicle_id' => $vehicle->id,
            'log_date' => '2024-01-25 00:00:00',
            'mileage' => 5678,
        ]);
    }

    public function test_mileage_log_can_be_deleted(): void
    {
        $user = $this->createUser();

        $mileageLog = $this->createMileageLog()['mileageLog'];

        $response = $this->actingAs($user)->delete(route('admin.mileage-logs.destroy', $mileageLog));

        $response->assertRedirect(route('admin.mileage-logs.index'));
        $this->assertDatabaseMissing('mileage_logs', [
            'id' => $mileageLog->id,
        ]);
    }

    // VALIDAZIONE DEI CAMPI OBBLIGATORI

    public function test_mileage_log_mileage_is_required(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.mileage-logs.store'), [
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-01-25',
        ]);

        // Verifica che il campo `mileage` sia obbligatorio.
        $response->assertSessionHasErrors(['mileage']);
        $this->assertDatabaseCount('mileage_logs', 0); // Conferma che non venga creato alcun record di log chilometrico senza chilometraggio.
    }
}
