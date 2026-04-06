<?php

namespace Tests\Feature\Crud;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeadlineCrudTest extends TestCase
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

    private function createDeadline()
    {
        $vehicle = $this->createVehicle();

        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'status' => 'renewed',
            'due_date' => '2025-01',
        ]);
        return (compact("vehicle", 'deadline'));
    }

    public function test_deadline_index_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.deadlines.index'));

        $response->assertStatus(200);
    }


    public function test_deadline_create_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user

        $response = $this->actingAs($user)->get(route('admin.deadlines.create'));

        $response->assertStatus(200);
    }



    public function test_deadline_show_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $deadline = $this->createDeadline()['deadline'];

        $response = $this->actingAs($user)->get(route('admin.deadlines.show', $deadline));

        $response->assertStatus(200);
    }

    public function test_deadline_edit_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $deadline = $this->createDeadline()['deadline'];

        $response = $this->actingAs($user)->get(route('admin.deadlines.edit', $deadline));

        $response->assertStatus(200);
    }


    public function test_deadline_can_be_stored(): void
    {
        $user = $this->createUser();    //fake user
        $vehicle = $this->createVehicle();


        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'status' => 'renewed',
            'due_date' => "2025-01",
        ]);

        $deadline = Deadline::latest('id')->first();

        $response->assertRedirect(route('admin.deadlines.show', $deadline));
        $this->assertDatabaseHas('deadlines', [
            'id' => $deadline->id,
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
        ]);
    }


    public function test_deadline_can_be_updated(): void
    {
        $user = $this->createUser();
        $data = $this->createDeadline();
        $deadline = $data['deadline'];
        $vehicle = $data['vehicle'];

        $response = $this->actingAs($user)->put(route('admin.deadlines.update', $deadline), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'status' => 'pending',
            'due_date' => '2025-01',
        ]);

        $response->assertRedirect(route('admin.deadlines.show', $deadline));
        $this->assertDatabaseHas('deadlines', [
            'id' => $deadline->id,
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
        ]);
    }

    public function test_deadline_can_be_deleted(): void
    {
        $user = $this->createUser();

        $deadline = $this->createDeadline()['deadline'];

        $response = $this->actingAs($user)->delete(route('admin.deadlines.destroy', $deadline));

        $response->assertRedirect(route('admin.deadlines.index'));
        $this->assertDatabaseMissing('deadlines', [
            'id' => $deadline->id,
        ]);
    }

    // VALIDAZIONE DEI CAMPI OBBLIGATORI

    public function test_deadline_type_is_required(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $count = Deadline::count();

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'status' => 'renewed',
        ]);

        // Verifica che il campo `type` sia obbligatorio.
        $response->assertSessionHasErrors(['type']);
        $this->assertDatabaseCount('deadlines', $count); // Conferma che non venga creato alcun record di scadenza senza tipo.
    }
}
