<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\Issue;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Roadmap 7b: vista a card dei guasti, raggruppata per veicolo, con
 * filtro di default sui soli guasti non chiusi.
 */
class IssueCardViewTest extends TestCase
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

    private function createVehicle(string $internalCode = '1234'): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat '.$internalCode]);
        $carModel = CarModel::create(['name' => 'Ducato '.$internalCode, 'brand_id' => $brand->id]);
        $vehicleType = VehicleType::create(['name' => 'Ambulanza '.$internalCode, 'needs_oxygen_check' => true]);

        return Vehicle::create([
            'license_plate' => 'AB'.rand(100, 999).'CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => $internalCode,
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ]);
    }

    public function test_card_view_hides_closed_issues_by_default(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'guasto-aperto-marker', 'status' => 'open', 'event_date' => '2026-01-01']);
        Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'guasto-chiuso-marker', 'status' => 'closed', 'event_date' => '2026-01-01']);

        $response = $this->actingAs($user)->get(route('admin.issues.index', ['view' => 'card']));

        $response->assertOk();
        $response->assertSee('guasto-aperto-marker');
        $response->assertDontSee('guasto-chiuso-marker');
    }

    public function test_table_view_still_shows_all_statuses_by_default(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'guasto-aperto-marker', 'status' => 'open', 'event_date' => '2026-01-01']);
        Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'guasto-chiuso-marker', 'status' => 'closed', 'event_date' => '2026-01-01']);

        $response = $this->actingAs($user)->get(route('admin.issues.index'));

        $response->assertOk();
        $response->assertSee('guasto-aperto-marker');
        $response->assertSee('guasto-chiuso-marker');
    }

    public function test_card_view_shows_closed_issues_when_explicitly_requested(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'guasto-chiuso-marker', 'status' => 'closed', 'event_date' => '2026-01-01']);

        $response = $this->actingAs($user)->get(route('admin.issues.index', ['view' => 'card', 'status_filter' => 'all']));

        $response->assertOk();
        $response->assertSee('guasto-chiuso-marker');
    }

    public function test_card_view_groups_issues_by_vehicle(): void
    {
        $user = $this->createUser();
        $vehicleA = $this->createVehicle('0001');
        $vehicleB = $this->createVehicle('0002');
        Issue::create(['vehicle_id' => $vehicleA->id, 'description' => 'guasto A', 'status' => 'open', 'event_date' => '2026-01-01']);
        Issue::create(['vehicle_id' => $vehicleB->id, 'description' => 'guasto B', 'status' => 'open', 'event_date' => '2026-01-01']);

        $response = $this->actingAs($user)->get(route('admin.issues.index', ['view' => 'card']));

        $response->assertOk();
        $response->assertSeeInOrder(['0001', 'guasto A', '0002', 'guasto B']);
    }

    public function test_table_view_is_the_default_when_no_view_param_is_given(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.issues.index'));

        $response->assertOk();
        $response->assertSee(__('Tabella'));
    }
}
