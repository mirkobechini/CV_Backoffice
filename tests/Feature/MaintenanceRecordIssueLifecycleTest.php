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
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MaintenanceRecordIssueLifecycleTest extends TestCase
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
            'resolved_issues' => [$issue->id => '1'],
            'resolved_deadlines' => [$deadline->id => '1'],
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

    public function test_complete_resolves_each_linked_issue_and_deadline_independently(): void
    {
        // Bug segnalato nell'audit 2026-10-19: complete() applicava un
        // unico sì/no a TUTTI i guasti/scadenze collegati allo stesso
        // appuntamento, impossibile risolverne uno sì e uno no.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $fixedIssue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto risolto',
            'status' => 'in_progress',
            'event_date' => '2025-01-02',
        ]);
        $unfixedIssue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto non risolto',
            'status' => 'in_progress',
            'event_date' => '2025-01-02',
        ]);
        $renewedDeadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'status' => 'pending',
            'due_date' => today()->addDays(10),
        ]);
        $notRenewedDeadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_OXYGEN,
            'status' => 'pending',
            'due_date' => today()->addDays(10),
        ]);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);
        $maintenance->items()->create(['itemable_id' => $fixedIssue->id, 'itemable_type' => Issue::class]);
        $maintenance->items()->create(['itemable_id' => $unfixedIssue->id, 'itemable_type' => Issue::class]);
        $maintenance->items()->create(['itemable_id' => $renewedDeadline->id, 'itemable_type' => Deadline::class]);
        $maintenance->items()->create(['itemable_id' => $notRenewedDeadline->id, 'itemable_type' => Deadline::class]);

        $this->actingAs($user)->patch(route('admin.maintenance-records.complete', $maintenance), [
            'resolved_issues' => [$fixedIssue->id => '1', $unfixedIssue->id => '0'],
            'resolved_deadlines' => [$renewedDeadline->id => '1', $notRenewedDeadline->id => '0'],
        ]);

        $this->assertDatabaseHas('issues', ['id' => $fixedIssue->id, 'status' => 'closed']);
        $this->assertDatabaseHas('issues', ['id' => $unfixedIssue->id, 'status' => 'in_progress']);
        $this->assertDatabaseHas('deadlines', ['id' => $renewedDeadline->id, 'status' => 'renewed']);
        $this->assertDatabaseHas('deadlines', ['id' => $notRenewedDeadline->id, 'status' => 'pending']);
        // Solo la scadenza rinnovata genera la successiva occorrenza.
        $this->assertDatabaseHas('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'status' => 'pending',
            'renews_deadline_id' => $renewedDeadline->id,
        ]);
        $this->assertDatabaseMissing('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_OXYGEN,
            'renews_deadline_id' => $notRenewedDeadline->id,
        ]);
    }

    public function test_completing_appointment_logs_issue_closure_in_activity_log(): void
    {
        // Prima di questo fix: la chiusura del guasto a completamento
        // avveniva con Issue::whereIn(...)->update(...), che bypassa gli
        // Eloquent event e quindi LogsActivity — nessuna voce finiva nello
        // storico, a differenza della modifica manuale di un guasto.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto da chiudere',
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
            'resolved_issues' => [$issue->id => '1'],
        ]);

        $this->assertTrue(
            Activity::where('subject_type', Issue::class)
                ->where('subject_id', $issue->id)
                ->where('event', 'updated')
                ->exists()
        );
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
            'resolved_issues' => [$issue->id => '0'],
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
            'resolved_issues' => [$issue->id => '1'],
        ]);

        // Il completamento va rifiutato: nessuna modifica al record o al guasto.
        $maintenance->refresh();
        $this->assertNull($maintenance->return_date);
        $this->assertDatabaseHas('issues', [
            'id' => $issue->id,
            'status' => 'in_progress',
        ]);
    }
}
