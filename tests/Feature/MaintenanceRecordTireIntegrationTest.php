<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\Tire;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceRecordTireIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    private function createVehicle(): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ]);
    }

    private function createProvider(): Provider
    {
        return Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);
    }

    private function createTire(Vehicle $vehicle, array $overrides = []): Tire
    {
        return Tire::create(array_merge([
            'vehicle_id' => $vehicle->id,
            'season' => 'winter',
            'axle' => 'full',
            'quantity' => 4,
            'status' => 'stored',
        ], $overrides));
    }

    public function test_creating_tire_change_appointment_links_existing_stored_tire(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $tire = $this->createTire($vehicle);

        $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->toDateString(),
            'activity_type' => 'Cambio Gomme',
            'target_tire_ids' => [$tire->id],
        ]);

        $record = MaintenanceRecord::first();

        $this->assertDatabaseHas('maintenance_record_items', [
            'maintenance_record_id' => $record->id,
            'itemable_id' => $tire->id,
            'itemable_type' => Tire::class,
            'completed' => false,
        ]);

        // Il set non viene montato alla creazione, solo al completamento.
        $this->assertSame('stored', $tire->fresh()->status);
    }

    public function test_creating_tire_change_appointment_can_link_multiple_existing_sets_together(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $front = $this->createTire($vehicle, ['axle' => 'front', 'quantity' => 2, 'season' => 'winter']);
        $rear = $this->createTire($vehicle, ['axle' => 'rear', 'quantity' => 2, 'season' => 'winter']);

        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->toDateString(),
            'activity_type' => 'Cambio Gomme',
            'target_tire_ids' => [$front->id, $rear->id],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $record = MaintenanceRecord::first();

        $this->assertDatabaseHas('maintenance_record_items', [
            'maintenance_record_id' => $record->id,
            'itemable_id' => $front->id,
            'itemable_type' => Tire::class,
        ]);
        $this->assertDatabaseHas('maintenance_record_items', [
            'maintenance_record_id' => $record->id,
            'itemable_id' => $rear->id,
            'itemable_type' => Tire::class,
        ]);
    }

    public function test_creating_tire_change_appointment_creates_new_tire_when_no_existing_selected(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->toDateString(),
            'activity_type' => 'Cambio Gomme',
            'new_tire_season' => 'summer',
            'new_tire_axle' => 'full',
            'new_tire_quantity' => 4,
            'new_tire_brand' => 'Pirelli',
        ]);

        $this->assertDatabaseHas('tires', [
            'vehicle_id' => $vehicle->id,
            'season' => 'summer',
            'axle' => 'full',
            'brand' => 'Pirelli',
            'status' => 'stored',
        ]);

        $record = MaintenanceRecord::first();
        $newTire = Tire::where('brand', 'Pirelli')->first();

        $this->assertDatabaseHas('maintenance_record_items', [
            'maintenance_record_id' => $record->id,
            'itemable_id' => $newTire->id,
            'itemable_type' => Tire::class,
        ]);
    }

    public function test_creating_tire_change_appointment_can_combine_existing_set_with_a_new_one(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $existingFront = $this->createTire($vehicle, ['axle' => 'front', 'quantity' => 2, 'season' => 'summer']);

        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->toDateString(),
            'activity_type' => 'Cambio Gomme',
            'target_tire_ids' => [$existingFront->id],
            'new_tire_season' => 'summer',
            'new_tire_axle' => 'rear',
            'new_tire_quantity' => 2,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $record = MaintenanceRecord::first();
        $this->assertSame(2, $record->items->where('itemable_type', Tire::class)->count());
    }

    public function test_creating_tire_change_appointment_rejects_selection_not_totaling_four(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $front = $this->createTire($vehicle, ['axle' => 'front', 'quantity' => 2, 'season' => 'winter']);

        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->toDateString(),
            'activity_type' => 'Cambio Gomme',
            'target_tire_ids' => [$front->id],
        ]);

        $response->assertSessionHasErrors(['target_tire_ids']);
        $this->assertDatabaseCount('maintenance_records', 0);
    }

    public function test_creating_tire_change_appointment_rejects_mixed_seasons(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $winterFront = $this->createTire($vehicle, ['axle' => 'front', 'quantity' => 2, 'season' => 'winter']);
        $summerRear = $this->createTire($vehicle, ['axle' => 'rear', 'quantity' => 2, 'season' => 'summer']);

        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->toDateString(),
            'activity_type' => 'Cambio Gomme',
            'target_tire_ids' => [$winterFront->id, $summerRear->id],
        ]);

        $response->assertSessionHasErrors(['target_tire_ids']);
        $this->assertDatabaseCount('maintenance_records', 0);
    }

    public function test_completing_appointment_with_multiple_linked_tires_mounts_both_and_disposes_previous_full_set(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $oldFull = $this->createTire($vehicle, ['axle' => 'full', 'quantity' => 4, 'season' => 'winter', 'status' => 'mounted']);
        $newFront = $this->createTire($vehicle, ['axle' => 'front', 'quantity' => 2, 'season' => 'summer', 'status' => 'stored']);
        $newRear = $this->createTire($vehicle, ['axle' => 'rear', 'quantity' => 2, 'season' => 'summer', 'status' => 'stored']);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => 'Cambio Gomme',
        ]);
        $maintenance->items()->create(['itemable_id' => $newFront->id, 'itemable_type' => Tire::class]);
        $maintenance->items()->create(['itemable_id' => $newRear->id, 'itemable_type' => Tire::class]);

        $this->actingAs($user)->patch(route('admin.maintenance-records.complete', $maintenance), [
            'issue_resolved' => '1',
            'previous_disposition' => 'stored',
        ]);

        $this->assertSame('mounted', $newFront->fresh()->status);
        $this->assertSame('mounted', $newRear->fresh()->status);
        // Il vecchio set "full" viene interamente sostituito da due nuovi
        // set (anteriori+posteriori): TireChangeService lo chiude come
        // "retired" (superato), non applica la disposition scelta perché
        // non è una singola rimozione fisica scelta dall'utente.
        $this->assertSame('retired', $oldFull->fresh()->status);

        $this->assertDatabaseHas('maintenance_record_items', [
            'maintenance_record_id' => $maintenance->id,
            'itemable_id' => $newFront->id,
            'completed' => true,
        ]);
        $this->assertDatabaseHas('maintenance_record_items', [
            'maintenance_record_id' => $maintenance->id,
            'itemable_id' => $newRear->id,
            'completed' => true,
        ]);
    }

    public function test_completing_tire_change_appointment_mounts_tire_and_stores_previous(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $oldTire = $this->createTire($vehicle, ['season' => 'winter', 'status' => 'mounted']);
        $newTire = $this->createTire($vehicle, ['season' => 'summer', 'status' => 'stored']);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => 'Cambio Gomme',
            'mileage_at_service' => 42000,
        ]);
        $maintenance->items()->create([
            'itemable_id' => $newTire->id,
            'itemable_type' => Tire::class,
        ]);

        $response = $this->actingAs($user)->patch(route('admin.maintenance-records.complete', $maintenance), [
            'issue_resolved' => '1',
            'previous_disposition' => 'stored',
        ]);

        $response->assertRedirect();
        $this->assertSame('mounted', $newTire->fresh()->status);
        $this->assertSame('stored', $oldTire->fresh()->status);
        $this->assertSame(42000, $newTire->fresh()->mounted_mileage);

        $this->assertDatabaseHas('tire_changes', [
            'vehicle_id' => $vehicle->id,
            'tire_id' => $newTire->id,
            'previous_tire_id' => $oldTire->id,
            'mileage_at_change' => 42000,
        ]);

        $this->assertDatabaseHas('maintenance_record_items', [
            'maintenance_record_id' => $maintenance->id,
            'itemable_id' => $newTire->id,
            'completed' => true,
        ]);
    }

    public function test_completing_tire_change_appointment_retires_previous_when_chosen(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $oldTire = $this->createTire($vehicle, ['status' => 'mounted']);
        $newTire = $this->createTire($vehicle, ['season' => 'summer', 'status' => 'stored']);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => 'Cambio Gomme',
        ]);
        $maintenance->items()->create([
            'itemable_id' => $newTire->id,
            'itemable_type' => Tire::class,
        ]);

        $this->actingAs($user)->patch(route('admin.maintenance-records.complete', $maintenance), [
            'issue_resolved' => '1',
            'previous_disposition' => 'retired',
        ]);

        $this->assertSame('retired', $oldTire->fresh()->status);
    }

    public function test_completing_tire_change_appointment_requires_disposition_choice(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $tire = $this->createTire($vehicle, ['status' => 'stored']);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => 'Cambio Gomme',
        ]);
        $maintenance->items()->create([
            'itemable_id' => $tire->id,
            'itemable_type' => Tire::class,
        ]);

        $response = $this->actingAs($user)->patch(route('admin.maintenance-records.complete', $maintenance), [
            'issue_resolved' => '1',
        ]);

        $response->assertSessionHasErrors(['previous_disposition']);
        $this->assertSame('stored', $tire->fresh()->status);
    }

    public function test_completing_appointment_without_tire_item_does_not_require_disposition(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => 'Tagliando',
        ]);

        $response = $this->actingAs($user)->patch(route('admin.maintenance-records.complete', $maintenance), [
            'issue_resolved' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
    }
}
