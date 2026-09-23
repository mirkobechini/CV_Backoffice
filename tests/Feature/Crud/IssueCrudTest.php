<?php

namespace Tests\Feature\Crud;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\Issue;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueCrudTest extends TestCase
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
            'group_id' => $this->defaultGroup()->id,
        ]);
    }

    private function createIssue()
    {
        $vehicle = $this->createVehicle();

        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'something',
            'status' => 'closed',
            'event_date' => '2025-01-02',
        ]);
        return (compact("vehicle", 'issue'));
    }

    public function test_issue_index_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.issues.index'));

        $response->assertStatus(200);
    }


    public function test_issue_create_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user

        $response = $this->actingAs($user)->get(route('admin.issues.create'));

        $response->assertStatus(200);
    }



    public function test_issue_show_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $issue = $this->createIssue()['issue'];

        $response = $this->actingAs($user)->get(route('admin.issues.show', $issue));

        $response->assertStatus(200);
    }

    public function test_issue_edit_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $issue = $this->createIssue()['issue'];

        $response = $this->actingAs($user)->get(route('admin.issues.edit', $issue));

        $response->assertStatus(200);
    }


    public function test_issue_can_be_stored(): void
    {
        $user = $this->createUser();    //fake user
        $vehicle = $this->createVehicle();


        $response = $this->actingAs($user)->post(route('admin.issues.store'), [
            'vehicle_id' => $vehicle->id,
            'description' => 'something',
            'status' => 'closed',
            'event_date' => '2025-01-02',
        ]);

        $issue = Issue::first();

        $response->assertRedirect(route('admin.issues.show', $issue));
        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'vehicle_id' => $vehicle->id,
            'description' => 'something',
            'status' => 'closed',
            'event_date' => '2025-01-02 00:00:00',
        ]);
    }


    public function test_issue_can_be_updated(): void
    {
        $user = $this->createUser();
        $data = $this->createIssue();
        $issue = $data['issue'];
        $vehicle = $data['vehicle'];

        $response = $this->actingAs($user)->put(route('admin.issues.update', $issue), [
            'vehicle_id' => $vehicle->id,
            'description' => 'something else',
            'status' => 'open',
            'event_date' => '2026-01-02',
        ]);

        $response->assertRedirect(route('admin.issues.show', $issue));
        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'vehicle_id' => $vehicle->id,
            'description' => 'something else',
            'status' => 'open',
            'event_date' => '2026-01-02 00:00:00',
        ]);
    }

    public function test_issue_can_be_deleted(): void
    {
        $user = $this->createUser();

        $issue = $this->createIssue()['issue'];

        $response = $this->actingAs($user)->delete(route('admin.issues.destroy', $issue));

        $response->assertRedirect(route('admin.issues.index'));
        $this->assertSoftDeleted($issue);
    }

    // VALIDAZIONE DEI CAMPI OBBLIGATORI

    public function test_issue_description_is_required(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.issues.store'), [
            'vehicle_id' => $vehicle->id,
            'status' => 'closed',
            'event_date' => '2025-01-02',
        ]);

        // Verifica che il campo `description` sia obbligatorio.
        $response->assertSessionHasErrors(['description']);
        $this->assertDatabaseCount('issues', 0); // Conferma che non venga creato alcun issue senza descrizione.
    }

    public function test_search_finds_issue_by_vehicle_internal_code(): void
    {
        // Il campo cerca "veicolo o descrizione" (vedi placeholder), ma
        // Issue::$searchable copre solo description/status: cercare il
        // codice del veicolo non trovava nulla.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Rumore al motore',
            'status' => 'open',
            'event_date' => '2025-01-02',
        ]);

        $response = $this->actingAs($user)->get(route('admin.issues.index', ['q' => $vehicle->internal_code]));

        $response->assertOk();
        $response->assertSee('Rumore al motore');
    }

    public function test_create_page_hides_tire_picker_behind_checkbox_by_default(): void
    {
        // Il selettore pneumatico non deve comparire per ogni guasto: resta
        // nascosto finché non si seleziona "Guasto relativo a un pneumatico".
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.issues.create'));

        $response->assertOk();
        $response->assertSee('Guasto relativo a un pneumatico');
        $response->assertSee('id="tire-id-field" style="display:none;"', false);
    }

    public function test_edit_page_shows_tire_picker_when_issue_already_has_a_tire(): void
    {
        $vehicle = $this->createVehicle();
        $tire = \App\Models\Tire::create([
            'vehicle_id' => $vehicle->id,
            'season' => 'summer',
            'quantity' => 4,
            'status' => 'mounted',
        ]);
        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'tire_id' => $tire->id,
            'description' => 'Foratura',
            'status' => 'open',
            'event_date' => '2025-01-02',
        ]);
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.issues.edit', $issue));

        $response->assertOk();
        $response->assertSee('id="is-tire-issue" checked', false);
        $response->assertDontSee('id="tire-id-field" style="display:none;"', false);
    }

    public function test_issue_can_still_be_stored_with_a_linked_tire(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $tire = \App\Models\Tire::create([
            'vehicle_id' => $vehicle->id,
            'season' => 'summer',
            'quantity' => 4,
            'status' => 'mounted',
        ]);

        $response = $this->actingAs($user)->post(route('admin.issues.store'), [
            'vehicle_id' => $vehicle->id,
            'tire_id' => $tire->id,
            'description' => 'Foratura',
            'status' => 'open',
            'event_date' => '2025-01-02',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('issues', [
            'vehicle_id' => $vehicle->id,
            'tire_id' => $tire->id,
        ]);
    }
}
