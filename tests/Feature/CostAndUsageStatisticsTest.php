<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\MileageLog;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\CostStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostAndUsageStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
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

    private function vehicle(string $internalCode = '0001', ?Group $group = null): Vehicle
    {
        $brand = Brand::firstOrCreate(['name' => 'Fiat']);
        $model = CarModel::firstOrCreate(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::firstOrCreate(
            ['name' => 'Ambulanza'],
            ['first_inspection_months' => 48, 'regular_inspection_months' => 24]
        );

        return Vehicle::create([
            'license_plate' => 'AB'.$internalCode.'CD',
            'internal_code' => $internalCode,
            'vehicle_type_id' => $type->id,
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => ($group ?? $this->defaultGroup())->id,
        ]);
    }

    private function provider(string $name = 'Officina Test'): Provider
    {
        return Provider::create(['name' => $name, 'type' => 'Meccanico']);
    }

    // --- Costi ---

    public function test_cost_by_year_sums_cost_grouped_by_appointment_year(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $provider = $this->provider();

        MaintenanceRecord::create(['vehicle_id' => $vehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2025-03-01', 'cost' => 100]);
        MaintenanceRecord::create(['vehicle_id' => $vehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2026-01-01', 'cost' => 50]);
        MaintenanceRecord::create(['vehicle_id' => $vehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2026-06-01', 'cost' => 30]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'costi', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('100,00');
        $response->assertSee('80,00');
    }

    public function test_cost_by_vehicle_and_provider(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle('0001');
        $provider = $this->provider('Officina Alfa');

        MaintenanceRecord::create(['vehicle_id' => $vehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2026-01-01', 'cost' => 150]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'costi', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('0001');
        $response->assertSee('Officina Alfa');
        $response->assertSeeInOrder(['Spesa per veicolo', '150,00']);
    }

    public function test_cost_statistics_exclude_another_groups_data(): void
    {
        $user = $this->admin();
        $otherGroup = Group::create(['name' => 'Altro gruppo', 'invite_code' => Group::generateInviteCode()]);
        $otherVehicle = $this->vehicle('9999', $otherGroup);
        $provider = $this->provider();

        MaintenanceRecord::create(['vehicle_id' => $otherVehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2026-01-01', 'cost' => 999]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'costi', 'period' => 'all']));

        $response->assertOk();
        $response->assertDontSee('999,00');
    }

    public function test_cost_per_km_excludes_vehicles_below_minimum_km(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $provider = $this->provider();

        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-01-01', 'mileage' => 1000]);
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-01-15', 'mileage' => 1000 + CostStatisticsService::MIN_KM_FOR_COST_PER_KM - 1]);
        MaintenanceRecord::create(['vehicle_id' => $vehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2026-01-10', 'cost' => 100]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'costi', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('Nessun veicolo con dati sufficienti');
    }

    public function test_cost_per_km_includes_vehicles_above_minimum_km(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $provider = $this->provider();

        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-01-01', 'mileage' => 1000]);
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-01-15', 'mileage' => 1200]);
        MaintenanceRecord::create(['vehicle_id' => $vehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2026-01-10', 'cost' => 100]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'costi', 'period' => 'all']));

        $response->assertOk();
        // 100€ / 200km = 0,50 €/km
        $response->assertSee('0,50');
    }

    // --- Affidabilità/Utilizzo ---

    public function test_km_traveled_by_vehicle_needs_at_least_two_readings(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-01-01', 'mileage' => 1000]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'affidabilita', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('Nessun veicolo con almeno due letture km');
    }

    public function test_km_traveled_by_vehicle_with_two_readings(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-01-01', 'mileage' => 1000]);
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-02-01', 'mileage' => 1500]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'affidabilita', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('500 km');
    }

    public function test_issue_count_by_vehicle_ranks_most_problematic_first(): void
    {
        $user = $this->admin();
        $vehicleA = $this->vehicle('0001');
        $vehicleB = $this->vehicle('0002');

        Issue::create(['vehicle_id' => $vehicleA->id, 'description' => 'a', 'status' => 'open', 'event_date' => '2026-01-01']);
        Issue::create(['vehicle_id' => $vehicleA->id, 'description' => 'b', 'status' => 'open', 'event_date' => '2026-01-02']);
        Issue::create(['vehicle_id' => $vehicleB->id, 'description' => 'c', 'status' => 'open', 'event_date' => '2026-01-03']);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'affidabilita', 'period' => 'all']));

        $response->assertOk();
        $response->assertSeeInOrder(['0001', '0002']);
    }

    public function test_average_downtime_by_provider_only_counts_completed_records(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $provider = $this->provider('Officina Beta');

        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-01',
            'return_date' => '2026-01-05',
        ]);
        // Non completato: non deve entrare nella media.
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2026-02-01',
        ]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'affidabilita', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('Officina Beta');
        $response->assertSee('4 giorni');
    }

    public function test_statistics_default_tab_is_guasti(): void
    {
        $user = $this->admin();

        $response = $this->actingAs($user)->get(route('admin.statistics.index'));

        $response->assertOk();
        $response->assertSee('Guasti per parte del veicolo');
    }
}
