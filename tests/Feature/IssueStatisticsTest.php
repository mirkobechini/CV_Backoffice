<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRecordItem;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    private function member(): User
    {
        return User::factory()->withRole('volunteer')->create();
    }

    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    private function vehicle(?Group $group = null): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);

        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'vehicle_type_id' => $type->id,
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => ($group ?? $this->defaultGroup())->id,
        ]);
    }

    public function test_member_cannot_access_statistics_page(): void
    {
        $user = $this->member();

        $response = $this->actingAs($user)->get(route('admin.statistics.index'));

        $response->assertForbidden();
    }

    public function test_capo_sees_counts_by_part(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        Issue::create(['vehicle_id' => $vehicle->id, 'part' => Issue::PART_FRENI, 'description' => 'a', 'status' => 'open', 'event_date' => '2026-01-01']);
        Issue::create(['vehicle_id' => $vehicle->id, 'part' => Issue::PART_FRENI, 'description' => 'b', 'status' => 'open', 'event_date' => '2026-01-02']);
        Issue::create(['vehicle_id' => $vehicle->id, 'part' => Issue::PART_MOTORE, 'description' => 'c', 'status' => 'open', 'event_date' => '2026-01-03']);

        $response = $this->actingAs($user)->get(route('admin.statistics.index'));

        $response->assertOk();
        $response->assertSee(Issue::PART_FRENI);
        $response->assertSeeInOrder([Issue::PART_FRENI, Issue::PART_MOTORE]);
    }

    public function test_statistics_exclude_another_groups_issues(): void
    {
        $user = $this->admin();
        $otherGroup = Group::create(['name' => 'Altro gruppo', 'invite_code' => Group::generateInviteCode()]);
        $otherVehicle = $this->vehicle($otherGroup);
        Issue::create(['vehicle_id' => $otherVehicle->id, 'part' => Issue::PART_CARROZZERIA, 'description' => 'non mio', 'status' => 'open', 'event_date' => '2026-01-01']);

        $response = $this->actingAs($user)->get(route('admin.statistics.index'));

        $response->assertOk();
        $response->assertDontSee(Issue::PART_CARROZZERIA);
    }

    public function test_vehicle_part_matrix_groups_by_vehicle(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        Issue::create(['vehicle_id' => $vehicle->id, 'part' => Issue::PART_FRENI, 'description' => 'a', 'status' => 'open', 'event_date' => '2026-01-01']);
        Issue::create(['vehicle_id' => $vehicle->id, 'part' => Issue::PART_FRENI, 'description' => 'b', 'status' => 'open', 'event_date' => '2026-01-02']);

        $response = $this->actingAs($user)->get(route('admin.statistics.index'));

        $response->assertOk();
        $response->assertSee('0001');
        $response->assertSee('2'); // conteggio nella cella della matrice
    }

    public function test_monthly_trend_includes_months_with_zero_issues(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        Issue::create(['vehicle_id' => $vehicle->id, 'part' => Issue::PART_FRENI, 'description' => 'a', 'status' => 'open', 'event_date' => now()->subMonths(3)]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index'));

        $response->assertOk();
        // 12 mesi mostrati (incluso quello corrente), anche quelli senza guasti.
        $response->assertSee(ucfirst(now()->locale('it')->translatedFormat('F Y')));
    }

    public function test_cost_by_part_splits_shared_appointment_cost_evenly(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $provider = Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);

        $issue1 = Issue::create(['vehicle_id' => $vehicle->id, 'part' => Issue::PART_FRENI, 'description' => 'a', 'status' => 'closed', 'event_date' => '2026-01-01']);
        $issue2 = Issue::create(['vehicle_id' => $vehicle->id, 'part' => Issue::PART_MOTORE, 'description' => 'b', 'status' => 'closed', 'event_date' => '2026-01-01']);

        $record = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-05',
            'return_date' => '2026-01-05',
            'cost' => 200,
        ]);
        MaintenanceRecordItem::create(['maintenance_record_id' => $record->id, 'itemable_id' => $issue1->id, 'itemable_type' => Issue::class, 'completed' => true]);
        MaintenanceRecordItem::create(['maintenance_record_id' => $record->id, 'itemable_id' => $issue2->id, 'itemable_type' => Issue::class, 'completed' => true]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index'));

        $response->assertOk();
        // 200€ diviso tra 2 guasti = 100€ a testa.
        $response->assertSee('100,00');
    }
}
