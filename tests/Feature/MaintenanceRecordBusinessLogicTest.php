<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Group;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MaintenanceRecordBusinessLogicTest extends TestCase
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

    private function createVehicle(array $overrides = []): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        return Vehicle::create(array_merge([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ], $overrides));
    }

    private function createProvider(): Provider
    {
        return Provider::create([
            'name' => 'Officina Test',
            'contact_info' => '+39 123456',
            'address' => 'Via Roma 1',
            'type' => 'Meccanico',
        ]);
    }

    public function test_store_sets_issue_to_in_progress(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Test issue',
            'status' => 'open',
            'event_date' => '2025-01-02',
        ]);

        $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'issue_ids' => [$issue->id],
            'appointment_date' => '2025/02/01',
        ]);

        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_update_removed_issue_returns_to_open(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Test issue',
            'status' => 'in_progress',
            'event_date' => '2025-01-02',
        ]);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2025/02/01',
        ]);
        $maintenance->items()->create([
            'itemable_id' => $issue->id,
            'itemable_type' => Issue::class,
        ]);

        // Aggiorno rimuovendo l'issue
        $this->actingAs($user)->put(route('admin.maintenance-records.update', $maintenance), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'issue_ids' => [],
            'appointment_date' => '2025/02/01',
        ]);

        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'status' => 'open',
        ]);
    }

    public function test_destroy_returns_issues_to_open(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Test issue',
            'status' => 'in_progress',
            'event_date' => '2025-01-02',
        ]);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2025/02/01',
        ]);
        $maintenance->items()->create([
            'itemable_id' => $issue->id,
            'itemable_type' => Issue::class,
        ]);

        $this->actingAs($user)->delete(route('admin.maintenance-records.destroy', $maintenance));

        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'status' => 'open',
        ]);
    }

    public function test_complete_with_issue_resolved_closes_issues_and_renews_deadline(): void
    {
        $user = $this->createUser();
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
            'brand_id' => null,
            'car_model_id' => null,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ]);
        $provider = $this->createProvider();

        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Test issue',
            'status' => 'in_progress',
            'event_date' => '2025-01-02',
        ]);

        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'status' => 'pending',
            'due_date' => today()->addDays(10),
        ]);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);
        $maintenance->items()->create([
            'itemable_id' => $issue->id,
            'itemable_type' => Issue::class,
        ]);
        $maintenance->items()->create([
            'itemable_id' => $deadline->id,
            'itemable_type' => Deadline::class,
        ]);

        $this->actingAs($user)->patch(route('admin.maintenance-records.complete', $maintenance), [
            'issue_resolved' => '1',
        ]);

        // L'issue deve essere chiuso
        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'status' => 'closed',
        ]);

        // La deadline deve essere rinnovata
        $this->assertDatabaseHas('deadlines', [
            'id' => $deadline->id,
            'status' => 'renewed',
        ]);

        // Una nuova deadline deve essere stata creata
        $this->assertDatabaseHas('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'status' => 'pending',
        ]);

        // La manutenzione deve avere return_date = oggi
        $maintenance->refresh();
        $this->assertNotNull($maintenance->return_date);
        $this->assertEquals(Carbon::today()->toDateString(), $maintenance->return_date->toDateString());
    }

    public function test_complete_with_issue_not_resolved_leaves_issue_in_progress(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Test issue',
            'status' => 'in_progress',
            'event_date' => '2025-01-02',
        ]);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);
        $maintenance->items()->create([
            'itemable_id' => $issue->id,
            'itemable_type' => Issue::class,
        ]);

        $this->actingAs($user)->patch(route('admin.maintenance-records.complete', $maintenance), [
            'issue_resolved' => '0',
        ]);

        // L'issue resta in_progress
        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_complete_rejects_appointment_not_yet_occurred(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Test issue',
            'status' => 'in_progress',
            'event_date' => '2025-01-02',
        ]);

        // Appuntamento la cui data non è ancora arrivata.
        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->addDays(15),
        ]);
        $maintenance->items()->create([
            'itemable_id' => $issue->id,
            'itemable_type' => Issue::class,
        ]);

        $this->actingAs($user)->patch(route('admin.maintenance-records.complete', $maintenance), [
            'issue_resolved' => '1',
        ]);

        // Il completamento va rifiutato: nessuna modifica al record o al guasto.
        $maintenance->refresh();
        $this->assertNull($maintenance->return_date);
        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_create_form_excludes_closed_issues_already_linked(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        // Guasto risolto NON collegato a un appuntamento
        $unlinkedIssue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto non collegato',
            'status' => 'closed',
        ]);

        // Guasto risolto GIÀ collegato a un appuntamento
        $linkedIssue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto già collegato',
            'status' => 'closed',
        ]);
        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);
        $maintenance->items()->create([
            'itemable_id' => $linkedIssue->id,
            'itemable_type' => Issue::class,
        ]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.create'));

        $response->assertOk();
        // Il guasto non collegato è presente
        $response->assertSee('Guasto non collegato');
        // Il guasto già collegato NON è presente
        $response->assertDontSee('Guasto già collegato');
    }

    public function test_create_form_hides_closed_issue_section_when_all_linked(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        // Guasto risolto GIÀ collegato a un appuntamento
        $linkedIssue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto già collegato',
            'status' => 'closed',
        ]);
        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);
        $maintenance->items()->create([
            'itemable_id' => $linkedIssue->id,
            'itemable_type' => Issue::class,
        ]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.create'));

        $response->assertOk();
        // La sezione guasti risolti NON deve essere renderizzata
        $response->assertDontSee('Guasti risolti (per registrare riparazioni avvenute)');
    }

    public function test_create_form_shows_dates_for_open_issues(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto con data',
            'status' => 'open',
            'event_date' => '2025-06-15',
        ]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.create'));

        $response->assertOk();
        // La data del guasto appare accanto alla descrizione
        $response->assertSee('15/06/2025');
    }

    public function test_create_form_includes_in_progress_issue_already_linked(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        // Guasto in lavorazione GIÀ collegato a un appuntamento precedente (non risolto)
        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto non risolto',
            'status' => 'in_progress',
        ]);
        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);
        $maintenance->items()->create([
            'itemable_id' => $issue->id,
            'itemable_type' => Issue::class,
        ]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.create'));

        $response->assertOk();
        // Il guasto in lavorazione deve restare selezionabile per un nuovo appuntamento
        $response->assertSee('Guasto non risolto');
    }

    public function test_edit_form_shows_closed_issues_for_selection(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        // Guasto risolto NON collegato: deve essere selezionabile in modifica
        $closedIssue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto risolto da aggiungere',
            'status' => 'closed',
            'event_date' => '2025-03-10',
        ]);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.edit', $maintenance));

        $response->assertOk();
        // Il guasto risolto deve essere selezionabile in modifica
        $response->assertSee('Guasto risolto da aggiungere');
        // La data appare accanto alla descrizione
        $response->assertSee('10/03/2025');
    }

    public function test_index_shows_all_issue_descriptions(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $issue1 = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Primo guasto',
            'status' => 'in_progress',
        ]);
        $issue2 = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Secondo guasto',
            'status' => 'in_progress',
        ]);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);
        $maintenance->items()->create(['itemable_id' => $issue1->id, 'itemable_type' => Issue::class]);
        $maintenance->items()->create(['itemable_id' => $issue2->id, 'itemable_type' => Issue::class]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.index'));

        $response->assertOk();
        // Entrambe le descrizioni dei guasti devono apparire
        $response->assertSee('Primo guasto');
        $response->assertSee('Secondo guasto');
    }

    public function test_index_shows_linked_deadlines_alongside_issues_on_the_same_row(): void
    {
        // Le scadenze collegate non comparivano affatto nell'elenco: solo i
        // guasti. Un appuntamento con guasto E scadenza deve mostrare
        // entrambi sulla stessa riga (non deve sparire nessuno dei due).
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Rumore sospetto',
            'status' => 'in_progress',
        ]);
        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => today()->addMonth(),
            'interval_km' => 20000,
        ]);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);
        $maintenance->items()->create(['itemable_id' => $issue->id, 'itemable_type' => Issue::class]);
        $maintenance->items()->create(['itemable_id' => $deadline->id, 'itemable_type' => Deadline::class]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.index'));

        $response->assertOk();
        $response->assertSee('Rumore sospetto');
        $response->assertSee('Tagliando');
        $response->assertSee('Scadenza'); // badge dedicato, in aggiunta a quello guasto
    }

    public function test_index_can_be_filtered_by_vehicle(): void
    {
        $user = $this->createUser();
        $vehicleA = $this->createVehicle();
        $brandB = Brand::create(['name' => 'Iveco']);
        $modelB = CarModel::create(['name' => 'Daily', 'brand_id' => $brandB->id]);
        $typeB = VehicleType::create(['name' => 'Furgone', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $vehicleB = Vehicle::create([
            'license_plate' => 'ZZ999ZZ',
            'internal_code' => '9999',
            'brand_id' => $brandB->id,
            'car_model_id' => $modelB->id,
            'vehicle_type_id' => $typeB->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ]);
        $provider = $this->createProvider();

        MaintenanceRecord::create([
            'vehicle_id' => $vehicleA->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => 'Attività veicolo A',
        ]);
        MaintenanceRecord::create([
            'vehicle_id' => $vehicleB->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => 'Attività veicolo B',
        ]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.index', ['vehicle_id' => $vehicleA->id]));

        $response->assertOk();
        $response->assertSee('Attività veicolo A');
        $response->assertDontSee('Attività veicolo B');
    }

    public function test_complete_with_issue_resolved_renews_tagliando(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Tagliando',
            'status' => 'in_progress',
            'event_date' => '2025-01-02',
        ]);

        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'status' => 'pending',
            'due_date' => today()->addDays(10),
        ]);

        $appointmentDate = Carbon::parse('2024-10-18');
        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => $appointmentDate,
            'return_date' => '2024-10-18',
            'mileage_at_service' => 16000,
        ]);
        $maintenance->items()->create([
            'itemable_id' => $issue->id,
            'itemable_type' => Issue::class,
        ]);
        $maintenance->items()->create([
            'itemable_id' => $deadline->id,
            'itemable_type' => Deadline::class,
        ]);

        $this->actingAs($user)->patch(route('admin.maintenance-records.complete', $maintenance), [
            'issue_resolved' => '1',
        ]);

        // Il tagliando deve essere rinnovato
        $this->assertDatabaseHas('deadlines', [
            'id' => $deadline->id,
            'status' => 'renewed',
            'is_renewed' => true,
        ]);

        // La nuova scadenza tagliando deve essere creata:
        // - data = data rientro + 12 mesi (18/10/2024 → 18/10/2025)
        // - last_mileage = km inseriti (16000)
        // - interval_km = intervallo del tipo veicolo (default 20000)
        $this->assertDatabaseHas('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'status' => 'pending',
            'due_date' => '2025-10-18 00:00:00',
            'last_mileage' => 16000,
            'interval_km' => 20000,
        ]);
    }

    public function test_store_requires_mileage_when_tagliando_selected(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'status' => 'pending',
            'due_date' => today()->addDays(10),
        ]);

        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'deadline_ids' => [$deadline->id],
            'appointment_date' => '2024/10/18',
            // mileage_at_service mancante
        ]);

        $response->assertSessionHasErrors('mileage_at_service');
    }

    public function test_complete_with_tagliando_revision_and_issue_together(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        // Guasto
        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto generico',
            'status' => 'in_progress',
            'event_date' => '2024-09-01',
        ]);

        // Tagliando
        $tagliando = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'status' => 'pending',
            'due_date' => today()->addDays(10),
        ]);

        // Revisione Ministeriale
        $revision = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'status' => 'pending',
            'due_date' => today()->addDays(5),
        ]);

        $appointmentDate = Carbon::parse('2024-10-18');
        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => $appointmentDate,
            'return_date' => '2024-10-18',
            'mileage_at_service' => 16000,
        ]);
        $maintenance->items()->create(['itemable_id' => $issue->id, 'itemable_type' => Issue::class]);
        $maintenance->items()->create(['itemable_id' => $tagliando->id, 'itemable_type' => Deadline::class]);
        $maintenance->items()->create(['itemable_id' => $revision->id, 'itemable_type' => Deadline::class]);

        $this->actingAs($user)->patch(route('admin.maintenance-records.complete', $maintenance), [
            'issue_resolved' => '1',
        ]);

        // Il guasto è chiuso
        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'status' => 'closed',
        ]);

        // Il tagliando è rinnovato e crea la nuova scadenza (data appuntamento + 12 mesi)
        $this->assertDatabaseHas('deadlines', [
            'id' => $tagliando->id,
            'status' => 'renewed',
            'is_renewed' => true,
        ]);
        $this->assertDatabaseHas('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'status' => 'pending',
            'due_date' => '2025-10-18 00:00:00',
            'last_mileage' => 16000,
            'interval_km' => 20000,
        ]);

        // La revisione è rinnovata e crea la nuova scadenza
        // (data appuntamento 18/10/2024 + 24 mesi = 18/10/2026)
        $this->assertDatabaseHas('deadlines', [
            'id' => $revision->id,
            'status' => 'renewed',
            'is_renewed' => true,
        ]);
        $this->assertDatabaseHas('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'status' => 'pending',
            'due_date' => '2026-10-18 00:00:00',
        ]);
    }

    public function test_store_with_return_date_and_completed_tagliando_creates_next_deadline(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $tagliando = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'status' => 'pending',
            'due_date' => '2023-12-30',
        ]);

        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'deadline_ids' => [$tagliando->id],
            'completed_deadline_ids' => [$tagliando->id],
            'appointment_date' => '2024/10/18',
            'return_date' => '2024/10/18',
            'mileage_at_service' => 16156,
        ]);

        $response->assertRedirect();

        // Il tagliando è rinnovato
        $this->assertDatabaseHas('deadlines', [
            'id' => $tagliando->id,
            'status' => 'renewed',
            'is_renewed' => true,
        ]);

        // La nuova scadenza tagliando è creata (data appuntamento + 12 mesi)
        $this->assertDatabaseHas('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'status' => 'pending',
            'due_date' => '2025-10-18 00:00:00',
            'last_mileage' => 16156,
            'interval_km' => 20000,
        ]);
    }

    public function test_store_with_return_date_and_completed_issue_closes_issue(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Spia olio lampeggia',
            'status' => 'closed',
            'event_date' => '2024-10-16',
        ]);

        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'issue_ids' => [$issue->id],
            'completed_issue_ids' => [$issue->id],
            'appointment_date' => '2024/10/18',
            'return_date' => '2024/10/18',
        ]);

        $response->assertRedirect();

        // Il guasto resta chiuso
        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'status' => 'closed',
        ]);
    }

    public function test_renewing_tagliando_twice_creates_two_new_deadlines(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $tagliando = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'status' => 'pending',
            'due_date' => '2023-12-30',
        ]);

        // Primo rinnovo
        $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'deadline_ids' => [$tagliando->id],
            'completed_deadline_ids' => [$tagliando->id],
            'appointment_date' => '2024/10/18',
            'return_date' => '2024/10/18',
            'mileage_at_service' => 16156,
        ]);

        // Secondo rinnovo (nuovo tagliando creato dal primo rinnovo)
        $secondTagliando = Deadline::where('vehicle_id', $vehicle->id)
            ->where('type', Deadline::TYPE_TAGLIANDO)
            ->where('id', '!=', $tagliando->id)
            ->first();

        $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'deadline_ids' => [$secondTagliando->id],
            'completed_deadline_ids' => [$secondTagliando->id],
            'appointment_date' => '2025/10/18',
            'return_date' => '2025/10/18',
            'mileage_at_service' => 30000,
        ]);

        // Devono esserci 4 tagliandi totali:
        // 1 dal VehicleObserver + 1 originale del test + 2 nuovi rinnovi
        $count = Deadline::where('vehicle_id', $vehicle->id)
            ->where('type', Deadline::TYPE_TAGLIANDO)
            ->count();

        $this->assertEquals(4, $count);
    }

    public function test_store_rejects_overlapping_appointment(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        // Appuntamento esistente dal 26/09 al 02/10
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2025-09-26',
            'return_date' => '2025-10-02',
        ]);

        // Nuovo appuntamento il 28/09 (sovrapposto)
        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2025-09-28',
        ]);

        $response->assertSessionHasErrors('appointment_date');
    }

    public function test_store_allows_non_overlapping_appointment(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        // Appuntamento esistente dal 26/09 al 02/10
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2025-09-26',
            'return_date' => '2025-10-02',
        ]);

        // Nuovo appuntamento il 05/10 (non sovrapposto)
        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2025-10-05',
        ]);

        $response->assertRedirect();
    }

    public function test_store_with_completed_cinghia_creates_next_deadline(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $cinghia = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
            'status' => 'pending',
            'due_date' => '2024-01-01',
        ]);

        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'deadline_ids' => [$cinghia->id],
            'completed_deadline_ids' => [$cinghia->id],
            'appointment_date' => '2024/10/18',
            'return_date' => '2024/10/18',
            'mileage_at_service' => 50000,
        ]);

        $response->assertRedirect();

        // La cinghia è rinnovata
        $this->assertDatabaseHas('deadlines', [
            'id' => $cinghia->id,
            'status' => 'renewed',
            'is_renewed' => true,
        ]);

        // Una nuova scadenza cinghia è creata (data rientro + 3650 giorni)
        $this->assertDatabaseHas('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
            'status' => 'pending',
            'last_mileage' => 50000,
            'interval_km' => 100000,
        ]);
    }

    public function test_store_requires_mileage_when_cinghia_selected(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $cinghia = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
            'status' => 'pending',
            'due_date' => '2024-01-01',
        ]);

        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'deadline_ids' => [$cinghia->id],
            'appointment_date' => '2024/10/18',
            'return_date' => '2024/10/18',
            // mileage_at_service mancante
        ]);

        $response->assertSessionHasErrors('mileage_at_service');
    }

    public function test_completing_appointment_links_next_deadline_to_the_renewed_one(): void
    {
        // Prima questo controller creava la scadenza successiva senza mai
        // impostare renews_deadline_id: la guardia anti-duplicati aggiunta
        // in DeadlineService (v1.2.2, per il rinnovo dal form di modifica)
        // non poteva riconoscere una scadenza creata da qui.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'status' => 'pending',
            'due_date' => '2024-06-30',
        ]);

        $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'deadline_ids' => [$deadline->id],
            'completed_deadline_ids' => [$deadline->id],
            'appointment_date' => '2024/06/18',
            'return_date' => '2024/06/18',
        ]);

        $next = Deadline::where('vehicle_id', $vehicle->id)
            ->where('type', Deadline::TYPE_MINISTERIAL)
            ->where('id', '!=', $deadline->id)
            ->first();

        $this->assertNotNull($next, 'La scadenza successiva non è stata creata.');
        $this->assertEquals($deadline->id, $next->renews_deadline_id);
    }

    public function test_completing_appointment_carries_over_mileage_to_ministerial_deadline(): void
    {
        // Il km rilevato all'appuntamento non veniva mai riportato sulla
        // scadenza per ministeriale/ossigeno (solo per tagliando/cinghia).
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'status' => 'pending',
            'due_date' => '2024-06-30',
        ]);

        $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'deadline_ids' => [$deadline->id],
            'completed_deadline_ids' => [$deadline->id],
            'appointment_date' => '2024/06/18',
            'return_date' => '2024/06/18',
            'mileage_at_service' => 72500,
        ]);

        $this->assertDatabaseHas('deadlines', [
            'id' => $deadline->id,
            'last_mileage' => 72500,
        ]);
    }

    public function test_deleting_appointment_removes_the_linked_next_deadline(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'status' => 'pending',
            'due_date' => '2024-06-30',
        ]);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2024-06-18',
        ]);
        $maintenance->items()->create([
            'itemable_id' => $deadline->id,
            'itemable_type' => Deadline::class,
        ]);

        $this->actingAs($user)->patch(route('admin.maintenance-records.complete', $maintenance), [
            'issue_resolved' => '1',
        ]);

        $next = Deadline::where('renews_deadline_id', $deadline->id)->first();
        $this->assertNotNull($next);

        $this->actingAs($user)->delete(route('admin.maintenance-records.destroy', $maintenance));

        $this->assertSoftDeleted('deadlines', ['id' => $next->id]);
        $this->assertDatabaseHas('deadlines', [
            'id' => $deadline->id,
            'is_renewed' => false,
            'status' => 'pending',
        ]);
    }

    public function test_completing_appointment_records_mileage_in_vehicle_history(): void
    {
        // Il km rilevato all'appuntamento restava isolato sulla scadenza
        // (last_mileage): non veniva mai registrato come lettura ufficiale
        // dello storico chilometraggi del veicolo.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'status' => 'pending',
            'due_date' => '2024-06-30',
        ]);

        $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'deadline_ids' => [$deadline->id],
            'completed_deadline_ids' => [$deadline->id],
            'appointment_date' => '2024/06/18',
            'return_date' => '2024/06/18',
            'mileage_at_service' => 72500,
        ]);

        $this->assertDatabaseHas('mileage_logs', [
            'vehicle_id' => $vehicle->id,
            'log_date' => '2024-06-18 00:00:00',
            'mileage' => 72500,
        ]);
    }
}
