<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Group;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\MileageLog;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MaintenanceRecordTagliandoRenewalTest extends TestCase
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
            'resolved_issues' => [$issue->id => '1'],
            'resolved_deadlines' => [$deadline->id => '1'],
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

    public function test_store_rejects_mileage_lower_than_previous_reading(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        MileageLog::create([
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-09-01',
            'mileage' => 90000,
        ]);

        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2025-09-15',
            'mileage_at_service' => 85000,
        ]);

        $response->assertSessionHasErrors('mileage_at_service');
    }

    public function test_store_allows_mileage_consistent_with_history(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        MileageLog::create([
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-09-01',
            'mileage' => 90000,
        ]);

        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2025-09-15',
            'mileage_at_service' => 91000,
        ]);

        $response->assertSessionDoesntHaveErrors('mileage_at_service');
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
            'resolved_issues' => [$issue->id => '1'],
            'resolved_deadlines' => [$tagliando->id => '1', $revision->id => '1'],
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
}
