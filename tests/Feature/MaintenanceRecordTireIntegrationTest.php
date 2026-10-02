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
            'position' => Tire::POSITION_FRONT_LEFT,
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

    public function test_creating_tire_change_appointment_can_link_multiple_existing_tires_together(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $frontLeft = $this->createTire($vehicle, ['position' => Tire::POSITION_FRONT_LEFT, 'season' => 'winter']);
        $rearLeft = $this->createTire($vehicle, ['position' => Tire::POSITION_REAR_LEFT, 'season' => 'winter']);

        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->toDateString(),
            'activity_type' => 'Cambio Gomme',
            'target_tire_ids' => [$frontLeft->id, $rearLeft->id],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $record = MaintenanceRecord::first();

        $this->assertDatabaseHas('maintenance_record_items', [
            'maintenance_record_id' => $record->id,
            'itemable_id' => $frontLeft->id,
            'itemable_type' => Tire::class,
        ]);
        $this->assertDatabaseHas('maintenance_record_items', [
            'maintenance_record_id' => $record->id,
            'itemable_id' => $rearLeft->id,
            'itemable_type' => Tire::class,
        ]);
    }

    public function test_creating_tire_change_appointment_allows_selecting_a_single_tire(): void
    {
        // Non è più richiesto coprire un set completo da 4: un cambio può
        // riguardare anche una sola gomma.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $frontLeft = $this->createTire($vehicle, ['position' => Tire::POSITION_FRONT_LEFT, 'season' => 'winter']);

        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->toDateString(),
            'activity_type' => 'Cambio Gomme',
            'target_tire_ids' => [$frontLeft->id],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('maintenance_records', 1);
    }

    public function test_creating_tire_change_appointment_creates_full_set_of_four_new_tires(): void
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
            'new_tire_group' => 'full_set',
            'new_tire_brand' => 'Pirelli',
        ]);

        $newTires = Tire::where('brand', 'Pirelli')->get();
        $this->assertCount(4, $newTires);
        $this->assertSame(Tire::POSITIONS, $newTires->pluck('position')->sort()->values()->all());

        $record = MaintenanceRecord::first();
        foreach ($newTires as $newTire) {
            $this->assertDatabaseHas('maintenance_record_items', [
                'maintenance_record_id' => $record->id,
                'itemable_id' => $newTire->id,
                'itemable_type' => Tire::class,
            ]);
        }
    }

    public function test_creating_tire_change_appointment_creates_single_new_tire_at_chosen_position(): void
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
            'new_tire_group' => 'single',
            'new_tire_position' => 'rear_right',
            'new_tire_brand' => 'Pirelli',
        ]);

        $this->assertDatabaseHas('tires', [
            'vehicle_id' => $vehicle->id,
            'season' => 'summer',
            'position' => 'rear_right',
            'brand' => 'Pirelli',
            'status' => 'stored',
        ]);
        $this->assertSame(1, Tire::where('brand', 'Pirelli')->count());
    }

    public function test_creating_tire_change_appointment_can_combine_existing_tire_with_new_ones(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $existingFrontLeft = $this->createTire($vehicle, ['position' => Tire::POSITION_FRONT_LEFT, 'season' => 'summer']);

        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->toDateString(),
            'activity_type' => 'Cambio Gomme',
            'target_tire_ids' => [$existingFrontLeft->id],
            'new_tire_season' => 'summer',
            'new_tire_group' => 'rear_pair',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $record = MaintenanceRecord::first();
        // 1 esistente (anteriore sinistra) + 2 nuove (posteriori) = 3.
        $this->assertSame(3, $record->items->where('itemable_type', Tire::class)->count());
    }

    public function test_creating_tire_change_appointment_rejects_duplicate_positions(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $frontLeft = $this->createTire($vehicle, ['position' => Tire::POSITION_FRONT_LEFT, 'season' => 'winter']);

        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->toDateString(),
            'activity_type' => 'Cambio Gomme',
            'target_tire_ids' => [$frontLeft->id],
            'new_tire_season' => 'winter',
            'new_tire_group' => 'single',
            'new_tire_position' => 'front_left',
        ]);

        $response->assertSessionHasErrors(['target_tire_ids']);
        $this->assertDatabaseCount('maintenance_records', 0);
    }

    public function test_creating_tire_change_appointment_rejects_mixed_seasons(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $winterFrontLeft = $this->createTire($vehicle, ['position' => Tire::POSITION_FRONT_LEFT, 'season' => 'winter']);
        $summerRearLeft = $this->createTire($vehicle, ['position' => Tire::POSITION_REAR_LEFT, 'season' => 'summer']);

        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->toDateString(),
            'activity_type' => 'Cambio Gomme',
            'target_tire_ids' => [$winterFrontLeft->id, $summerRearLeft->id],
        ]);

        $response->assertSessionHasErrors(['target_tire_ids']);
        $this->assertDatabaseCount('maintenance_records', 0);
    }

    public function test_completing_appointment_with_multiple_linked_tires_mounts_both_and_leaves_untouched_positions_alone(): void
    {
        // Cambio parziale: solo le anteriori. Le posteriori, montate a parte,
        // non devono essere toccate — questo è esattamente il comportamento
        // "cambio 1, 2 o 4 gomme" richiesto.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $oldFrontLeft = $this->createTire($vehicle, ['position' => Tire::POSITION_FRONT_LEFT, 'season' => 'winter', 'status' => 'mounted']);
        $oldFrontRight = $this->createTire($vehicle, ['position' => Tire::POSITION_FRONT_RIGHT, 'season' => 'winter', 'status' => 'mounted']);
        $rearLeft = $this->createTire($vehicle, ['position' => Tire::POSITION_REAR_LEFT, 'season' => 'winter', 'status' => 'mounted']);
        $rearRight = $this->createTire($vehicle, ['position' => Tire::POSITION_REAR_RIGHT, 'season' => 'winter', 'status' => 'mounted']);
        $newFrontLeft = $this->createTire($vehicle, ['position' => Tire::POSITION_FRONT_LEFT, 'season' => 'summer', 'status' => 'stored']);
        $newFrontRight = $this->createTire($vehicle, ['position' => Tire::POSITION_FRONT_RIGHT, 'season' => 'summer', 'status' => 'stored']);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => 'Cambio Gomme',
        ]);
        $maintenance->items()->create(['itemable_id' => $newFrontLeft->id, 'itemable_type' => Tire::class]);
        $maintenance->items()->create(['itemable_id' => $newFrontRight->id, 'itemable_type' => Tire::class]);

        $this->actingAs($user)->patch(route('admin.maintenance-records.complete', $maintenance), [
            'issue_resolved' => '1',
            'previous_disposition' => [
                $newFrontLeft->id => 'stored',
                $newFrontRight->id => 'stored',
            ],
        ]);

        $this->assertSame('mounted', $newFrontLeft->fresh()->status);
        $this->assertSame('mounted', $newFrontRight->fresh()->status);
        $this->assertSame('stored', $oldFrontLeft->fresh()->status);
        $this->assertSame('stored', $oldFrontRight->fresh()->status);
        // Le posteriori non erano coinvolte nell'appuntamento: restano montate.
        $this->assertSame('mounted', $rearLeft->fresh()->status);
        $this->assertSame('mounted', $rearRight->fresh()->status);

        $this->assertDatabaseHas('maintenance_record_items', [
            'maintenance_record_id' => $maintenance->id,
            'itemable_id' => $newFrontLeft->id,
            'completed' => true,
        ]);
        $this->assertDatabaseHas('maintenance_record_items', [
            'maintenance_record_id' => $maintenance->id,
            'itemable_id' => $newFrontRight->id,
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
            'previous_disposition' => [$newTire->id => 'stored'],
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
            'previous_disposition' => [$newTire->id => 'retired'],
        ]);

        $this->assertSame('retired', $oldTire->fresh()->status);
    }

    public function test_completing_tire_change_appointment_allows_independent_disposition_per_tire(): void
    {
        // Cambio di 4 gomme nello stesso appuntamento: ognuna può avere una
        // disposizione diversa (es. 1 dismessa, 3 in magazzino), non più
        // un'unica scelta valida per tutte.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $oldFrontLeft = $this->createTire($vehicle, ['position' => Tire::POSITION_FRONT_LEFT, 'status' => 'mounted']);
        $oldFrontRight = $this->createTire($vehicle, ['position' => Tire::POSITION_FRONT_RIGHT, 'status' => 'mounted']);
        $oldRearLeft = $this->createTire($vehicle, ['position' => Tire::POSITION_REAR_LEFT, 'status' => 'mounted']);
        $oldRearRight = $this->createTire($vehicle, ['position' => Tire::POSITION_REAR_RIGHT, 'status' => 'mounted']);
        $newFrontLeft = $this->createTire($vehicle, ['position' => Tire::POSITION_FRONT_LEFT, 'status' => 'stored']);
        $newFrontRight = $this->createTire($vehicle, ['position' => Tire::POSITION_FRONT_RIGHT, 'status' => 'stored']);
        $newRearLeft = $this->createTire($vehicle, ['position' => Tire::POSITION_REAR_LEFT, 'status' => 'stored']);
        $newRearRight = $this->createTire($vehicle, ['position' => Tire::POSITION_REAR_RIGHT, 'status' => 'stored']);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => 'Cambio Gomme',
        ]);
        foreach ([$newFrontLeft, $newFrontRight, $newRearLeft, $newRearRight] as $tire) {
            $maintenance->items()->create(['itemable_id' => $tire->id, 'itemable_type' => Tire::class]);
        }

        $response = $this->actingAs($user)->patch(route('admin.maintenance-records.complete', $maintenance), [
            'issue_resolved' => '1',
            'previous_disposition' => [
                $newFrontLeft->id => 'retired',
                $newFrontRight->id => 'stored',
                $newRearLeft->id => 'stored',
                $newRearRight->id => 'stored',
            ],
        ]);

        $response->assertRedirect();
        $this->assertSame('retired', $oldFrontLeft->fresh()->status);
        $this->assertSame('stored', $oldFrontRight->fresh()->status);
        $this->assertSame('stored', $oldRearLeft->fresh()->status);
        $this->assertSame('stored', $oldRearRight->fresh()->status);
    }

    public function test_completing_tire_change_appointment_requires_disposition_choice(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        // Serve una gomma già montata nella stessa posizione: solo lì c'è
        // davvero qualcosa da smontare/disporre, quindi la scelta diventa
        // obbligatoria (un primo montaggio non la richiederebbe, vedi test
        // sotto).
        $oldTire = $this->createTire($vehicle, ['status' => 'mounted']);
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
        $this->assertSame('mounted', $oldTire->fresh()->status);
    }

    public function test_completing_tire_change_appointment_does_not_require_disposition_on_first_mount(): void
    {
        // Nessuna gomma montata in quella posizione: è un primo montaggio,
        // non c'è nulla da disporre, quindi nessuna scelta deve essere richiesta.
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

        $response->assertSessionDoesntHaveErrors(['previous_disposition']);
        $this->assertSame('mounted', $tire->fresh()->status);
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
