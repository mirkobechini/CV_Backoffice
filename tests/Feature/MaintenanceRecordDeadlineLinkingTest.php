<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Group;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceRecordDeadlineLinkingTest extends TestCase
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
            'resolved_deadlines' => [$deadline->id => '1'],
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
