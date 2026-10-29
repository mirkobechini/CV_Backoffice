<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\Tire;
use App\Models\TireChange;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TireStatisticsTest extends TestCase
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

    private function provider(string $name = 'Gommista Test'): Provider
    {
        return Provider::create(['name' => $name, 'type' => 'Gommista']);
    }

    private function tire(Vehicle $vehicle): Tire
    {
        return Tire::create([
            'vehicle_id' => $vehicle->id,
            'season' => Tire::SEASON_ALL_SEASON,
            'position' => Tire::POSITION_FRONT_LEFT,
            'status' => Tire::STATUS_MOUNTED,
        ]);
    }

    public function test_tire_cost_by_year_only_counts_tire_change_activity(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $provider = $this->provider();

        MaintenanceRecord::create(['vehicle_id' => $vehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2026-01-01', 'activity_type' => MaintenanceRecord::ACTIVITY_TIRE_CHANGE, 'cost' => 200]);
        // Non è un cambio gomme: non deve entrare nel totale.
        MaintenanceRecord::create(['vehicle_id' => $vehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2026-02-01', 'activity_type' => MaintenanceRecord::ACTIVITY_TAGLIANDO, 'cost' => 500]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'pneumatici', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('200,00');
        $response->assertDontSee('500,00');
    }

    public function test_tire_cost_by_vehicle(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle('0001');
        $provider = $this->provider();

        MaintenanceRecord::create(['vehicle_id' => $vehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2026-01-01', 'activity_type' => MaintenanceRecord::ACTIVITY_TIRE_CHANGE, 'cost' => 320]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'pneumatici', 'period' => 'all']));

        $response->assertOk();
        $response->assertSeeInOrder(['Spesa cambio gomme per veicolo', '0001', '320,00']);
    }

    public function test_tire_change_frequency_counts_tire_changes(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle('0001');
        $tireA = $this->tire($vehicle);
        $tireB = $this->tire($vehicle);

        TireChange::create(['vehicle_id' => $vehicle->id, 'tire_id' => $tireA->id, 'changed_date' => '2026-01-01', 'mileage_at_change' => 1000]);
        TireChange::create(['vehicle_id' => $vehicle->id, 'tire_id' => $tireB->id, 'changed_date' => '2026-06-01', 'mileage_at_change' => 1500]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'pneumatici', 'period' => 'all']));

        $response->assertOk();
        $response->assertSeeInOrder(['Numero cambi gomme per veicolo', '0001', '2']);
    }

    public function test_tire_statistics_exclude_another_groups_data(): void
    {
        $user = $this->admin();
        $otherGroup = Group::create(['name' => 'Altro gruppo', 'invite_code' => Group::generateInviteCode()]);
        $otherVehicle = $this->vehicle('9999', $otherGroup);
        $provider = $this->provider();

        MaintenanceRecord::create(['vehicle_id' => $otherVehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2026-01-01', 'activity_type' => MaintenanceRecord::ACTIVITY_TIRE_CHANGE, 'cost' => 999]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'pneumatici', 'period' => 'all']));

        $response->assertOk();
        $response->assertDontSee('999,00');
    }

    public function test_average_km_between_changes_averages_consecutive_changes(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $tireA = $this->tire($vehicle);
        $tireB = $this->tire($vehicle);
        $tireC = $this->tire($vehicle);

        // 20.000 km tra il primo e il secondo cambio.
        TireChange::create(['vehicle_id' => $vehicle->id, 'tire_id' => $tireA->id, 'changed_date' => '2025-01-01', 'mileage_at_change' => 10000]);
        TireChange::create(['vehicle_id' => $vehicle->id, 'tire_id' => $tireB->id, 'changed_date' => '2025-06-01', 'mileage_at_change' => 30000]);
        // 10.000 km tra il secondo e il terzo. Media: (20.000 + 10.000) / 2 = 15.000.
        TireChange::create(['vehicle_id' => $vehicle->id, 'tire_id' => $tireC->id, 'changed_date' => '2026-01-01', 'mileage_at_change' => 40000]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'pneumatici', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('15.000 km');
    }
}
