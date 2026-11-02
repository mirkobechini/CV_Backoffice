<?php

namespace Tests\Feature\Crud;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\Issue;
use App\Models\Tire;
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

        return compact('vehicle', 'issue');
    }

    public function test_issue_index_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.issues.index'));

        $response->assertStatus(200);
    }

    public function test_issue_create_page_is_reachable(): void
    {
        $user = $this->createUser();    // fake user

        $response = $this->actingAs($user)->get(route('admin.issues.create'));

        $response->assertStatus(200);
    }

    public function test_issue_show_page_is_reachable(): void
    {
        $user = $this->createUser();    // fake user
        $issue = $this->createIssue()['issue'];

        $response = $this->actingAs($user)->get(route('admin.issues.show', $issue));

        $response->assertStatus(200);
    }

    public function test_issue_edit_page_is_reachable(): void
    {
        $user = $this->createUser();    // fake user
        $issue = $this->createIssue()['issue'];

        $response = $this->actingAs($user)->get(route('admin.issues.edit', $issue));

        $response->assertStatus(200);
    }

    public function test_issue_can_be_stored(): void
    {
        $user = $this->createUser();    // fake user
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.issues.store'), [
            'vehicle_id' => $vehicle->id,
            'part' => Issue::PART_MOTORE,
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
            'part' => Issue::PART_MOTORE,
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
            'part' => Issue::PART_MOTORE,
            'status' => 'closed',
            'event_date' => '2025-01-02',
        ]);

        // Verifica che il campo `description` sia obbligatorio.
        $response->assertSessionHasErrors(['description']);
        $this->assertDatabaseCount('issues', 0); // Conferma che non venga creato alcun issue senza descrizione.
    }

    public function test_issue_part_is_required(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.issues.store'), [
            'vehicle_id' => $vehicle->id,
            'description' => 'something',
            'status' => 'closed',
            'event_date' => '2025-01-02',
        ]);

        $response->assertSessionHasErrors(['part']);
        $this->assertDatabaseCount('issues', 0);
    }

    public function test_issue_rejects_part_outside_fixed_vocabulary(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.issues.store'), [
            'vehicle_id' => $vehicle->id,
            'part' => 'Non esiste',
            'description' => 'something',
            'status' => 'closed',
            'event_date' => '2025-01-02',
        ]);

        $response->assertSessionHasErrors(['part']);
        $this->assertDatabaseCount('issues', 0);
    }

    public function test_index_shows_and_can_group_by_part(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        Issue::create(['vehicle_id' => $vehicle->id, 'part' => Issue::PART_FRENI, 'description' => 'Frena male', 'status' => 'open', 'event_date' => '2026-01-01']);

        $response = $this->actingAs($user)->get(route('admin.issues.index', ['group_by' => 'part']));

        $response->assertOk();
        $response->assertSee(Issue::PART_FRENI);
    }

    public function test_index_shows_backfill_link_only_when_issues_without_part_exist(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $withLink = $this->actingAs($user)->get(route('admin.issues.index'));
        $withLink->assertDontSee('Allinea parti');

        Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Senza parte', 'status' => 'open', 'event_date' => '2026-01-01']);

        $withoutLink = $this->actingAs($user)->get(route('admin.issues.index'));
        $withoutLink->assertSee('Allinea parti');
    }

    public function test_sorted_parts_is_alphabetical(): void
    {
        $sorted = Issue::sortedParts();

        $this->assertSame($sorted, collect($sorted)->sort()->values()->all());
        $this->assertSame(count(Issue::PARTS), count($sorted));
        $this->assertEmpty(array_diff(Issue::PARTS, $sorted));
    }

    public function test_create_page_lists_parts_alphabetically(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.issues.create'));

        $response->assertStatus(200);
        $content = $response->getContent();
        // "Allestimento" viene prima di "Motore" in ordine alfabetico, ma
        // dopo nella costante Issue::PARTS (ordine "logico").
        $this->assertTrue(strpos($content, 'Allestimento') < strpos($content, 'Motore'));
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

    public function test_create_page_includes_open_issue_of_vehicle_for_duplicate_check(): void
    {
        // Chi segnala un nuovo guasto deve vedere quelli già aperti sullo
        // stesso veicolo, per non duplicare una segnalazione già nota
        // scrivendola con parole diverse.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        Issue::create([
            'vehicle_id' => $vehicle->id,
            'part' => 'Freni',
            'description' => 'Rumore in frenata',
            'status' => 'open',
            'event_date' => '2026-01-01',
        ]);

        $response = $this->actingAs($user)->get(route('admin.issues.create'));

        $response->assertOk();
        $response->assertSee('open-issue-item', false);
        $response->assertSee('data-description="Rumore in frenata"', false);
        $response->assertSee('data-vehicle-id="'.$vehicle->id.'"', false);
    }

    public function test_create_page_excludes_closed_issues_from_open_issues_panel(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        Issue::create([
            'vehicle_id' => $vehicle->id,
            'part' => 'Freni',
            'description' => 'Problema già risolto',
            'status' => 'closed',
            'event_date' => '2026-01-01',
        ]);

        $response = $this->actingAs($user)->get(route('admin.issues.create'));

        $response->assertOk();
        $response->assertDontSee('data-description="Problema già risolto"', false);
    }

    public function test_create_page_hides_tire_picker_by_default(): void
    {
        // Il selettore pneumatico non deve comparire per ogni guasto: resta
        // nascosto finché non si seleziona "Pneumatici" come parte coinvolta
        // (si attiva via JS sul cambio della select, non più da una checkbox
        // separata).
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.issues.create'));

        $response->assertOk();
        $response->assertDontSee('Guasto relativo a uno o più pneumatici');
        $response->assertSee('id="tire-id-field" style="display:none;"', false);
    }

    public function test_edit_page_shows_tire_picker_when_issue_already_has_a_tire(): void
    {
        $vehicle = $this->createVehicle();
        $tire = Tire::create([
            'vehicle_id' => $vehicle->id,
            'season' => 'summer',
            'position' => Tire::POSITION_FRONT_LEFT,
            'status' => 'mounted',
        ]);
        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Foratura',
            'status' => 'open',
            'event_date' => '2025-01-02',
        ]);
        $issue->tires()->sync([$tire->id]);
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.issues.edit', $issue));

        $response->assertOk();
        $response->assertDontSee('id="tire-id-field" style="display:none;"', false);
    }

    public function test_issue_can_still_be_stored_with_a_linked_tire(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $tire = Tire::create([
            'vehicle_id' => $vehicle->id,
            'season' => 'summer',
            'position' => Tire::POSITION_FRONT_LEFT,
            'status' => 'mounted',
        ]);

        $response = $this->actingAs($user)->post(route('admin.issues.store'), [
            'vehicle_id' => $vehicle->id,
            'tire_ids' => [$tire->id],
            'part' => Issue::PART_PNEUMATICI,
            'description' => 'Foratura',
            'status' => 'open',
            'event_date' => '2025-01-02',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('issues', [
            'vehicle_id' => $vehicle->id,
        ]);
        $issue = Issue::where('vehicle_id', $vehicle->id)->firstOrFail();
        $this->assertDatabaseHas('issue_tire', [
            'issue_id' => $issue->id,
            'tire_id' => $tire->id,
        ]);
    }
}
