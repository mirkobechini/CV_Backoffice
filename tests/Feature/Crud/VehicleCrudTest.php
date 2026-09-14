<?php

namespace Tests\Feature\Crud;

use App\Models\User;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;


use Tests\TestCase;

class VehicleCrudTest extends TestCase
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
            'group_id' => $this->defaultGroup()->id,
        ]);

        return compact('brand', 'carModel', 'vehicleType', 'vehicle');
    }

    // TEST DI RAGGIUNGIBILITÀ DELLE PAGINE

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
        $this->assertSoftDeleted($vehicle);
    }

    // VALIDAZIONE UNICITÀ

    public function test_vehicle_cannot_be_stored_with_duplicate_license_plate()
    {
        $user = $this->createUser();
        $data = $this->createVehicle();

        $vehicle = $data['vehicle'];
        $brand = $data['brand'];
        $carModel = $data['carModel'];
        $vehicleType = $data['vehicleType'];

        // Forza il ritorno alla form in caso di errore di validazione.
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

        // Verifica che la targa duplicata venga rifiutata.
        $response->assertSessionHasErrors(['license_plate']);
        $this->assertDatabaseCount('vehicles', 1); // Conferma che non venga creato un secondo veicolo con la stessa targa.

    }


    public function test_vehicle_cannot_be_updated_with_duplicate_license_plate()
    {
        $user = $this->createUser();
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
            'group_id' => $this->defaultGroup()->id,
        ]);

        // Forza il ritorno alla form di modifica in caso di errore.
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

        // Verifica che l'update con targa duplicata venga bloccato.
        $response->assertSessionHasErrors(['license_plate']);
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'license_plate' => $vehicle->license_plate
        ]); // Conferma che il secondo veicolo mantenga la sua targa originale.

    }

    public function test_open_issues_badge_shows_the_count_only_once(): void
    {
        // Il conteggio veniva stampato due volte: una volta a mano subito
        // dopo l'icona, e una seconda volta dentro la stringa tradotta
        // (segnaposto :count), producendo un badge tipo "⚠ 3 3 guasto/i aperto/i".
        $user = $this->createUser();
        $vehicle = $this->createVehicle()['vehicle'];
        \App\Models\Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Motore non parte',
            'status' => 'open',
        ]);
        \App\Models\Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Freni da controllare',
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->get(route('admin.vehicles.show', $vehicle));

        $response->assertOk();
        $response->assertSee('2 guasto/i aperto/i');
        $response->assertDontSee('2 2 guasto/i aperto/i');
    }

    public function test_open_and_in_progress_issues_are_listed_before_closed_ones(): void
    {
        // La card guasti era ordinata solo per data: un guasto aperto più
        // vecchio finiva sotto uno chiuso più recente, invece di comparire
        // sempre in cima a prescindere dalla data.
        $user = $this->createUser();
        $vehicle = $this->createVehicle()['vehicle'];

        \App\Models\Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto chiuso recente',
            'status' => 'closed',
            'event_date' => '2025-06-01',
        ]);
        \App\Models\Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto aperto vecchio',
            'status' => 'open',
            'event_date' => '2024-01-01',
        ]);
        \App\Models\Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto in lavorazione',
            'status' => 'in_progress',
            'event_date' => '2024-06-01',
        ]);

        $response = $this->actingAs($user)->get(route('admin.vehicles.show', $vehicle));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Guasto in lavorazione', // più recente tra i non chiusi
            'Guasto aperto vecchio',
            'Guasto chiuso recente', // chiuso: ultimo nonostante la data più recente
        ]);
    }

    public function test_turning_on_timing_belt_prompts_to_create_the_deadline(): void
    {
        // has_timing_belt da solo non doveva creare/eliminare nulla: qui
        // verifichiamo solo che compaia il banner di conferma, non che la
        // scadenza venga creata in automatico.
        $user = $this->createUser();
        $data = $this->createVehicle();
        $vehicle = $data['vehicle'];

        $response = $this->actingAs($user)->from(route('admin.vehicles.edit', $vehicle))->put(route('admin.vehicles.update', $vehicle), [
            'license_plate' => $vehicle->license_plate,
            'vehicle_type_id' => $data['vehicleType']->id,
            'internal_code' => $vehicle->internal_code,
            'brand_id' => $data['brand']->id,
            'car_model_id' => $data['carModel']->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => $vehicle->immatricolation_date->format('Y-m-d'),
            'has_timing_belt' => '1',
        ]);

        $response->assertSessionHas('timingBeltPrompt', fn($prompt) => $prompt['action'] === 'create');
        $this->assertDatabaseMissing('deadlines', ['vehicle_id' => $vehicle->id, 'type' => \App\Models\Deadline::TYPE_CINGHIA]);
    }

    public function test_confirming_timing_belt_prompt_creates_the_deadline_from_immatriculation_date(): void
    {
        $user = $this->createUser();
        $data = $this->createVehicle();
        $vehicle = $data['vehicle'];
        $vehicle->update(['immatricolation_date' => '2020-01-15']);
        $expectedDueDate = \Carbon\Carbon::parse('2020-01-15')->addDays(\App\Models\Deadline::TIMING_BELT_INTERVAL_DAYS);

        $response = $this->actingAs($user)->post(route('admin.vehicles.timing-belt-deadline.create', $vehicle));

        $response->assertRedirect(route('admin.vehicles.show', $vehicle));
        $this->assertDatabaseHas('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => \App\Models\Deadline::TYPE_CINGHIA,
            'due_date' => $expectedDueDate->format('Y-m-d 00:00:00'),
        ]);
    }

    public function test_turning_off_timing_belt_prompts_to_delete_the_active_deadline(): void
    {
        $user = $this->createUser();
        $data = $this->createVehicle();
        $vehicle = $data['vehicle'];
        $vehicle->update(['has_timing_belt' => true]);
        $deadline = \App\Models\Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => \App\Models\Deadline::TYPE_CINGHIA,
            'due_date' => '2030-01-15',
            'is_renewed' => false,
        ]);

        $response = $this->actingAs($user)->put(route('admin.vehicles.update', $vehicle), [
            'license_plate' => $vehicle->license_plate,
            'vehicle_type_id' => $data['vehicleType']->id,
            'internal_code' => $vehicle->internal_code,
            'brand_id' => $data['brand']->id,
            'car_model_id' => $data['carModel']->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => $vehicle->immatricolation_date->format('Y-m-d'),
            'has_timing_belt' => '0',
        ]);

        $response->assertSessionHas('timingBeltPrompt', fn($prompt) => $prompt['action'] === 'delete' && $prompt['deadline_id'] === $deadline->id);
        $this->assertDatabaseHas('deadlines', ['id' => $deadline->id, 'deleted_at' => null]);
    }
}
