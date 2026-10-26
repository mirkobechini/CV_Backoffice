<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceRecordSchedulingTest extends TestCase
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

    public function test_cost_is_optional_and_saved_when_provided(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        // Senza costo: non deve bloccare il salvataggio (campo opzionale).
        $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2026-02-01',
        ])->assertSessionDoesntHaveErrors('cost');

        $withoutCost = MaintenanceRecord::first();
        $this->assertNull($withoutCost->cost);

        // Con costo: viene salvato e aggiornabile.
        $response = $this->actingAs($user)->put(route('admin.maintenance-records.update', $withoutCost), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2026-02-01',
            'cost' => '123.45',
        ]);

        $response->assertSessionDoesntHaveErrors('cost');
        $this->assertSame('123.45', $withoutCost->fresh()->cost);
    }

    public function test_update_without_changing_dates_ignores_preexisting_overlap_with_another_appointment(): void
    {
        // Due appuntamenti già esistenti e sovrapposti (es. creati prima
        // che questo controllo esistesse): modificare uno dei due senza
        // toccare le date (es. solo le note) non deve essere bloccato dal
        // conflitto preesistente, che la modifica non introduce né aggrava.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2026-07-31',
            'return_date' => '2026-07-31',
        ]);

        $editing = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2026-07-31',
            'return_date' => '2026-07-31',
        ]);

        $response = $this->actingAs($user)->put(route('admin.maintenance-records.update', $editing), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2026-07-31',
            'return_date' => '2026-07-31',
            'notes' => 'Nota aggiornata',
        ]);

        $response->assertSessionDoesntHaveErrors('appointment_date');
        $response->assertRedirect(route('admin.maintenance-records.show', $editing->id));
        $this->assertSame('Nota aggiornata', $editing->fresh()->notes);
    }

    public function test_update_still_rejects_overlap_when_dates_actually_change(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2025-09-26',
            'return_date' => '2025-10-02',
        ]);

        $editing = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2025-10-05',
        ]);

        // Sposta la data dell'appuntamento dentro quella dell'altro, già esistente.
        $response = $this->actingAs($user)->put(route('admin.maintenance-records.update', $editing), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2025-09-28',
        ]);

        $response->assertSessionHasErrors('appointment_date');
    }
}
