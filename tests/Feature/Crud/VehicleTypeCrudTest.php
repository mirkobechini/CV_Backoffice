<?php

namespace Tests\Feature\Crud;

use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTypeCrudTest extends TestCase
{

    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->create();
    }

    private function createVehicleType(): VehicleType
    {

        return VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);
    }

    public function test_vehicle_type_index_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.vehicle-types.index'));

        $response->assertStatus(200);
    }


    public function test_vehicle_type_create_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user

        $response = $this->actingAs($user)->get(route('admin.vehicle-types.create'));

        $response->assertStatus(200);
    }



    public function test_vehicle_type_show_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $vehicleType = $this->createVehicleType();

        $response = $this->actingAs($user)->get(route('admin.vehicle-types.show', $vehicleType));

        $response->assertStatus(200);
    }

    public function test_vehicle_type_edit_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $vehicleType = $this->createVehicleType();

        $response = $this->actingAs($user)->get(route('admin.vehicle-types.edit', $vehicleType));

        $response->assertStatus(200);
    }


    public function test_vehicle_type_can_be_stored(): void
    {
        $user = $this->createUser();    //fake user

        $response = $this->actingAs($user)->post(route('admin.vehicle-types.store'), [
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);

         $vehicleType = VehicleType::first();

        $response->assertRedirect(route('admin.vehicle-types.show',$vehicleType));

        $this->assertDatabaseHas('vehicle_types', [
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);
    }


    public function test_vehicle_type_can_be_updated(): void
    {
        $user = $this->createUser();
        $vehicleType = $this->createVehicleType();

        $response = $this->actingAs($user)->put(route('admin.vehicle-types.update', $vehicleType), [
            'name' => 'Auto',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        $response->assertRedirect(route('admin.vehicle-types.show', $vehicleType));

        $this->assertDatabaseHas('vehicle_types', [
            'name' => 'Auto',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);
    }

    public function test_vehicle_type_can_be_deleted(): void
    {
        $user = $this->createUser();

        $vehicleType = $this->createVehicleType();

        $response = $this->actingAs($user)->delete(route('admin.vehicle-types.destroy', $vehicleType));

        $response->assertRedirect(route('admin.vehicle-types.index'));
        $this->assertDatabaseMissing('vehicle_types', [
            'id' => $vehicleType->id,
        ]);
    }

    // VALIDAZIONE UNICITÀ

    public function test_vehicle_type_cannot_be_stored_with_duplicate_name()
    {
        $user = $this->createUser();
        $vehicleType = $this->createVehicleType();

        // Forza il ritorno alla form in caso di errore di validazione.
        $response = $this->from(route('admin.vehicle-types.create'))
            ->actingAs($user)->post(route('admin.vehicle-types.store'), [
                'name' => $vehicleType->name,
                'needs_oxygen_check' => true,
                'first_inspection_months' => 12,
                'regular_inspection_months' => 12,
            ]);

        // Verifica che il nome duplicato venga rifiutato.
        $response->assertSessionHasErrors(['name']);
        $this->assertDatabaseCount('vehicle_types', 1); // Conferma che non venga creato un secondo tipo di veicolo con lo stesso nome.

    }


    public function test_vehicle_type_cannot_be_updated_with_duplicate_name()
    {
        $user = $this->createUser();
        $vehicleTypeBase = $this->createVehicleType();
        $vehicleType = VehicleType::create([
            'name' => 'Auto',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);

        // Forza il ritorno alla form di modifica in caso di errore.
        $response = $this->from(route('admin.vehicle-types.edit', $vehicleType))->actingAs($user)->put(route('admin.vehicle-types.update', $vehicleType), [
                'name' => $vehicleTypeBase->name,
                'needs_oxygen_check' => true,
                'first_inspection_months' => 12,
                'regular_inspection_months' => 12,
            ]);

        // Verifica che l'update con nome duplicato venga bloccato.
        $response->assertSessionHasErrors(['name']);
        $this->assertDatabaseHas('vehicle_types', [
            'id' => $vehicleTypeBase->id,
            'name' => $vehicleTypeBase->name
        ]); // Conferma che il tipo di veicolo mantenga il nome originale.

    }
}
