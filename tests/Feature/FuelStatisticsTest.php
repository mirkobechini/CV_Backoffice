<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\FuelLog;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelStatisticsTest extends TestCase
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

    private function vehicle(string $internalCode = '0001', ?string $fuelType = 'diesel', ?Group $group = null): Vehicle
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
            'fuel_type' => $fuelType,
            'immatricolation_date' => '2024-01-01',
            'group_id' => ($group ?? $this->defaultGroup())->id,
        ]);
    }

    public function test_liters_and_cost_by_year_sums_grouped_by_log_date_year(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();

        FuelLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2025-03-01', 'liters' => 40, 'cost_total' => 60]);
        FuelLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-01-01', 'liters' => 30, 'cost_total' => 45]);
        FuelLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-06-01', 'liters' => 20, 'cost_total' => 30]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'carburante', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('40,00 L');
        $response->assertSee('50,00 L');
    }

    public function test_liters_and_cost_by_vehicle(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle('0001');

        FuelLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-01-01', 'liters' => 45.5, 'cost_total' => 68.90]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'carburante', 'period' => 'all']));

        $response->assertOk();
        $response->assertSeeInOrder(['Litri e spesa per veicolo', '0001', '45,50 L', '68,90']);
    }

    public function test_liters_and_cost_by_fuel_type(): void
    {
        $user = $this->admin();
        $dieselVehicle = $this->vehicle('0001', 'diesel');
        $electricVehicle = $this->vehicle('0002', 'elettrico');

        FuelLog::create(['vehicle_id' => $dieselVehicle->id, 'log_date' => '2026-01-01', 'liters' => 40, 'cost_total' => 60]);
        FuelLog::create(['vehicle_id' => $electricVehicle->id, 'log_date' => '2026-01-02', 'liters' => 10, 'cost_total' => 15]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'carburante', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('Diesel');
        $response->assertSee('Elettrico');
    }

    public function test_fuel_statistics_exclude_another_groups_data(): void
    {
        $user = $this->admin();
        $otherGroup = Group::create(['name' => 'Altro gruppo', 'invite_code' => Group::generateInviteCode()]);
        $otherVehicle = $this->vehicle('9999', group: $otherGroup);

        FuelLog::create(['vehicle_id' => $otherVehicle->id, 'log_date' => '2026-01-01', 'liters' => 999, 'cost_total' => 999]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'carburante', 'period' => 'all']));

        $response->assertOk();
        $response->assertDontSee('999,00');
    }

    public function test_average_consumption_by_vehicle_needs_two_fuel_logs_with_mileage(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();

        FuelLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-01-01', 'liters' => 40, 'mileage' => 1000]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'carburante', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('Nessun veicolo con almeno due rifornimenti consecutivi');
    }

    public function test_average_consumption_by_vehicle_averages_consecutive_fill_ups(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();

        // Primo pieno: nessun precedente, non entra nella media.
        FuelLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-01-01', 'liters' => 40, 'mileage' => 1000]);
        // 500 km / 25 L = 20 km/L
        FuelLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-01-15', 'liters' => 25, 'mileage' => 1500]);
        // 300 km / 30 L = 10 km/L. Media: (20 + 10) / 2 = 15 km/L.
        FuelLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-01-25', 'liters' => 30, 'mileage' => 1800]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'carburante', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('15,00 km/L');
    }
}
