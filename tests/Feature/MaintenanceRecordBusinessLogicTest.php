<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
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
        return User::factory()->create(['role' => 'admin']);
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
}
