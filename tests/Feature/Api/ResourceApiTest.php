<?php

namespace Tests\Feature\Api;

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
use Tests\TestCase;

class ResourceApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Stesso gruppo usato dallo stato "admin" di UserFactory::withRole(), in
     * modo che il veicolo creato da vehicle() sia sempre nel gruppo
     * dell'utente autenticato da authToken() (le route API sono scoperte
     * per gruppo: un veicolo senza gruppo o di un gruppo diverso non
     * sarebbe visibile/accessibile all'utente di questi test).
     */
    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    private function authToken(): string
    {
        $user = User::factory()->withRole('admin')->create();

        return $user->createToken('test')->plainTextToken;
    }

    private function vehicle(): Vehicle
    {
        $vt = VehicleType::create(['name' => 'Ambulanza', 'needs_oxygen_check' => true, 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);

        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vt->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ]);
    }

    public function test_vehicles_index_requires_auth(): void
    {
        $this->getJson('/api/vehicles')->assertStatus(401);
    }

    public function test_vehicles_index_returns_paginated_list(): void
    {
        $this->vehicle();
        $response = $this->withToken($this->authToken())->getJson('/api/vehicles');
        $response->assertOk()
            ->assertJsonStructure(['data', 'total', 'per_page']);
    }

    public function test_vehicles_show_returns_vehicle_with_relations(): void
    {
        $vehicle = $this->vehicle();
        $response = $this->withToken($this->authToken())->getJson("/api/vehicles/{$vehicle->id}");
        $response->assertOk()
            ->assertJsonPath('internal_code', '1234')
            ->assertJsonPath('brand.name', 'Fiat');
    }

    public function test_vehicles_suggestions_matches_internal_code_and_plate(): void
    {
        $vehicle = $this->vehicle();
        $response = $this->withToken($this->authToken())->getJson('/api/vehicles/suggestions?q=1234');
        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $vehicle->id)
            ->assertJsonPath('0.internal_code', '1234');

        $response = $this->withToken($this->authToken())->getJson('/api/vehicles/suggestions?q=AB123CD');
        $response->assertOk()->assertJsonCount(1);
    }

    public function test_vehicles_suggestions_does_not_leak_other_groups_vehicles(): void
    {
        $vehicle = $this->vehicle();

        $otherGroup = Group::create(['name' => 'Altro Gruppo', 'invite_code' => 'ZZZZ9999']);
        $otherVehicle = $vehicle->replicate();
        $otherVehicle->license_plate = 'ZZ999ZZ';
        $otherVehicle->internal_code = '1239';
        $otherVehicle->group_id = $otherGroup->id;
        $otherVehicle->save();

        $response = $this->withToken($this->authToken())->getJson('/api/vehicles/suggestions?q=123');

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $vehicle->id);
    }

    public function test_issues_index_returns_list(): void
    {
        $vehicle = $this->vehicle();
        Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Guasto motore', 'event_date' => '2025-01-01']);
        $response = $this->withToken($this->authToken())->getJson('/api/issues');
        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_issues_suggestions_returns_grouped_descriptions(): void
    {
        $vehicle = $this->vehicle();
        Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Guasto motore', 'event_date' => '2025-01-01']);
        Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Guasto motore', 'event_date' => '2025-02-01']);
        $response = $this->withToken($this->authToken())->getJson('/api/issues/suggestions?q=Guasto');
        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.description', 'Guasto motore')
            ->assertJsonPath('0.total', 2);
    }

    public function test_issues_suggestions_does_not_leak_other_groups_descriptions(): void
    {
        // Audit sicurezza 2026-10-22 (SH5): l'endpoint non filtrava per
        // gruppo, mostrando suggerimenti basati su descrizioni di
        // qualunque associazione.
        $vehicle = $this->vehicle();
        Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Guasto motore', 'event_date' => '2025-01-01']);

        $otherGroup = Group::create(['name' => 'Altro Gruppo', 'invite_code' => 'ZZZZ9999']);
        $otherVehicle = $vehicle->replicate();
        $otherVehicle->license_plate = 'ZZ999ZZ';
        $otherVehicle->internal_code = '9999';
        $otherVehicle->group_id = $otherGroup->id;
        $otherVehicle->save();
        Issue::create(['vehicle_id' => $otherVehicle->id, 'description' => 'Guasto freni altro gruppo', 'event_date' => '2025-01-01']);

        $response = $this->withToken($this->authToken())->getJson('/api/issues/suggestions?q=Guasto');

        $response->assertOk()->assertJsonCount(1)->assertJsonPath('0.description', 'Guasto motore');
    }

    public function test_deadlines_index_returns_list(): void
    {
        $vehicle = $this->vehicle();
        Deadline::create(['vehicle_id' => $vehicle->id, 'type' => Deadline::TYPE_MINISTERIAL, 'due_date' => '2025-06-01']);
        $response = $this->withToken($this->authToken())->getJson('/api/deadlines');
        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_maintenance_records_index_returns_list(): void
    {
        $vehicle = $this->vehicle();
        $provider = Provider::create(['name' => 'Officina', 'type' => 'Meccanico']);
        MaintenanceRecord::create(['vehicle_id' => $vehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2025-02-01']);
        $response = $this->withToken($this->authToken())->getJson('/api/maintenance-records');
        $response->assertOk()
            ->assertJsonStructure(['data']);
    }
}
