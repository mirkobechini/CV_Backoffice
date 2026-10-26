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

class MaintenanceRecordTireCompletionTest extends TestCase
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
        ]);

        $response->assertSessionDoesntHaveErrors(['previous_disposition']);
        $this->assertSame('mounted', $tire->fresh()->status);
    }

    /**
     * Regressione: salvare l'appuntamento dal form di modifica normale
     * (PUT update, non la dedicata PATCH complete) compilando la data di
     * rientro lasciava la gomma collegata "da montare al completamento"
     * anche a intervento segnato come concluso (bug segnalato 2026-10-15).
     */
}
