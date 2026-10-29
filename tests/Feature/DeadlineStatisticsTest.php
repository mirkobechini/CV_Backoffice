<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\DeadlineInsuranceCoverage;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeadlineStatisticsTest extends TestCase
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

    public function test_count_by_status_groups_expired_and_valid_deadlines(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();

        Deadline::create(['vehicle_id' => $vehicle->id, 'type' => Deadline::TYPE_TAGLIANDO, 'due_date' => Carbon::today()->subMonth(), 'is_renewed' => false]);
        Deadline::create(['vehicle_id' => $vehicle->id, 'type' => Deadline::TYPE_TAGLIANDO, 'due_date' => Carbon::today()->addYear(), 'is_renewed' => false]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'scadenze', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('Scaduta');
        $response->assertSee('Valida');
    }

    public function test_renewed_deadlines_do_not_count_as_expired(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();

        Deadline::create(['vehicle_id' => $vehicle->id, 'type' => Deadline::TYPE_TAGLIANDO, 'due_date' => Carbon::today()->subMonth(), 'is_renewed' => true]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'scadenze', 'period' => 'all']));

        $response->assertOk();
        $response->assertDontSee('Scaduta');
    }

    public function test_expired_count_by_type(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();

        Deadline::create(['vehicle_id' => $vehicle->id, 'type' => Deadline::TYPE_TAGLIANDO, 'due_date' => Carbon::today()->subMonth(), 'is_renewed' => false]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'scadenze', 'period' => 'all']));

        $response->assertOk();
        $response->assertSeeInOrder(['Scadenze scadute per tipo', Deadline::TYPE_TAGLIANDO]);
    }

    public function test_average_insurance_renewal_cost_sums_coverages_per_deadline(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();

        $deadlineA = Deadline::create(['vehicle_id' => $vehicle->id, 'type' => Deadline::TYPE_ASSICURAZIONE, 'due_date' => '2026-01-01', 'is_renewed' => false]);
        DeadlineInsuranceCoverage::create(['deadline_id' => $deadlineA->id, 'coverage_type' => 'RCA', 'cost' => 300]);
        DeadlineInsuranceCoverage::create(['deadline_id' => $deadlineA->id, 'coverage_type' => 'Kasko', 'cost' => 100]);

        $deadlineB = Deadline::create(['vehicle_id' => $vehicle->id, 'type' => Deadline::TYPE_ASSICURAZIONE, 'due_date' => '2026-02-01', 'is_renewed' => false]);
        DeadlineInsuranceCoverage::create(['deadline_id' => $deadlineB->id, 'coverage_type' => 'RCA', 'cost' => 200]);

        // Deadline senza coperture: non deve abbassare la media.
        Deadline::create(['vehicle_id' => $vehicle->id, 'type' => Deadline::TYPE_ASSICURAZIONE, 'due_date' => '2026-03-01', 'is_renewed' => false]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'scadenze', 'period' => 'all']));

        $response->assertOk();
        // Media tra 400 (A) e 200 (B) = 300.
        $response->assertSee('300,00');
    }

    public function test_deadline_statistics_exclude_another_groups_data(): void
    {
        $user = $this->admin();
        $otherGroup = Group::create(['name' => 'Altro gruppo', 'invite_code' => Group::generateInviteCode()]);
        $otherVehicle = $this->vehicle('9999', $otherGroup);

        Deadline::create(['vehicle_id' => $otherVehicle->id, 'type' => Deadline::TYPE_TAGLIANDO, 'due_date' => Carbon::today()->subMonth(), 'is_renewed' => false]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'scadenze', 'period' => 'all']));

        $response->assertOk();
        $response->assertDontSee('Scaduta');
    }
}
