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

class MaintenanceRecordTimingBeltRenewalTest extends TestCase
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

    public function test_completing_timing_belt_appointment_renews_the_deadline(): void
    {
        // Prima di questo fix: renewTimingBeltDeadline() veniva chiamato con
        // un solo argomento invece dei due richiesti (ArgumentCountError),
        // e comunque non sarebbe mai stato raggiunto perché il passo
        // "rinnova le scadenze collegate" esclude apposta TYPE_CINGHIA (la
        // cinghia non è collegata come MaintenanceRecordItem, a differenza
        // di tagliando/ministeriale/ossigeno).
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $vehicle->update(['timing_belt_type' => 'dry_belt']);
        $provider = $this->createProvider();

        $cinghia = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
            'status' => 'pending',
            'interval_km' => Deadline::TIMING_BELT_INTERVAL_KM,
            'last_mileage' => 0,
            'is_renewed' => false,
        ]);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => MaintenanceRecord::ACTIVITY_TIMING_BELT,
            'mileage_at_service' => 60000,
        ]);

        $response = $this->actingAs($user)->patch(route('admin.maintenance-records.complete', $maintenance), [
            'timing_belt_completed' => '1',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('deadlines', [
            'id' => $cinghia->id,
            'status' => 'renewed',
            'is_renewed' => true,
        ]);

        // Veicolo a secco: la nuova scadenza è solo a km, senza data.
        $this->assertDatabaseHas('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
            'status' => 'pending',
            'last_mileage' => 60000,
            'interval_km' => Deadline::TIMING_BELT_INTERVAL_KM,
            'interval_days' => null,
            'due_date' => null,
        ]);
    }

    public function test_completing_timing_belt_appointment_creates_deadline_when_none_exists(): void
    {
        // Caso reale: il tipo di distribuzione è stato impostato/corretto
        // dopo la creazione del veicolo, senza passare dal prompt "crea
        // scadenza cinghia" — il veicolo non ha mai avuto una scadenza
        // cinghia. Prima di questo fix, completare l'appuntamento non
        // trovava nulla da rinnovare e non faceva assolutamente nulla in
        // silenzio: il cambio veniva registrato ma nessuna scadenza
        // nasceva mai.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $vehicle->update(['timing_belt_type' => 'dry_belt']);
        $provider = $this->createProvider();

        $this->assertDatabaseMissing('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
        ]);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => MaintenanceRecord::ACTIVITY_TIMING_BELT,
            'mileage_at_service' => 60000,
        ]);

        $response = $this->actingAs($user)->patch(route('admin.maintenance-records.complete', $maintenance), [
            'timing_belt_completed' => '1',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
            'status' => 'pending',
            'is_renewed' => false,
            'last_mileage' => 60000,
            'interval_km' => Deadline::TIMING_BELT_INTERVAL_KM,
            'due_date' => null,
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
}
