<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\Tire;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Copertura della stima automatica "prossimo cambio" per gomme montate
 * senza un valore manuale (vedi TireController::attachSuggestedNextChangeDate()).
 */
class TireNextChangeDateTest extends TestCase
{
    use RefreshDatabase;

    private ?Group $group = null;

    private function admin(): User
    {
        $user = User::factory()->create();
        $this->group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $this->group->addUser($user, Group::ROLE_CAPO);

        return $user;
    }

    private function createVehicle(): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat '.uniqid()]);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza '.uniqid(), 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);

        return Vehicle::create([
            'group_id' => $this->group?->id,
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => '2024-01-01',
        ]);
    }

    private function createTire(Vehicle $vehicle, array $overrides = []): Tire
    {
        return Tire::create(array_merge([
            'vehicle_id' => $vehicle->id,
            'season' => 'winter',
            'position' => Tire::POSITION_FRONT_LEFT,
            'brand' => 'Michelin',
            'model_name' => 'Alpin 6',
            'size' => '205/55R16',
            'status' => 'stored',
        ], $overrides));
    }

    public function test_index_shows_estimated_next_change_for_compliant_mounted_tire_without_manual_date(): void
    {
        // Gruppo di default: cambio invernali 15/11, estive 15/04. Il
        // 1/12 la stagione attesa è invernale: una gomma invernale montata
        // è in regola, quindi stimabile un prossimo cambio (alle estive).
        $this->travelTo(Carbon::parse('2026-12-01'));

        $user = $this->admin();
        $vehicle = $this->createVehicle();
        $this->createTire($vehicle, ['season' => 'winter', 'status' => 'mounted', 'next_change_date' => null]);

        $response = $this->actingAs($user)->get(route('admin.tires.index'));

        $response->assertOk();
        $response->assertSee('15/04/2027');
        $response->assertSee(__('stimato'));
    }

    public function test_index_prefers_manual_next_change_date_over_estimate(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01'));

        $user = $this->admin();
        $vehicle = $this->createVehicle();
        $this->createTire($vehicle, ['season' => 'winter', 'status' => 'mounted', 'next_change_date' => '2027-01-10']);

        $response = $this->actingAs($user)->get(route('admin.tires.index'));

        $response->assertOk();
        $response->assertSee('10/01/2027');
        $response->assertDontSee(__('stimato'));
    }

    public function test_index_does_not_estimate_for_tire_already_out_of_season(): void
    {
        // Il 1/12 la stagione attesa è invernale: una gomma estiva montata
        // è già fuori stagione — nessuna stima (sarebbe fuorviante,
        // cadrebbe l'anno successivo).
        $this->travelTo(Carbon::parse('2026-12-01'));

        $user = $this->admin();
        $vehicle = $this->createVehicle();
        $this->createTire($vehicle, ['season' => 'summer', 'status' => 'mounted', 'next_change_date' => null]);

        $response = $this->actingAs($user)->get(route('admin.tires.index'));

        $response->assertOk();
        $response->assertDontSee(__('stimato'));
    }

    public function test_index_does_not_estimate_for_stored_tire(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01'));

        $user = $this->admin();
        $vehicle = $this->createVehicle();
        $this->createTire($vehicle, ['season' => 'winter', 'status' => 'stored', 'next_change_date' => null]);

        $response = $this->actingAs($user)->get(route('admin.tires.index'));

        $response->assertOk();
        $response->assertDontSee(__('stimato'));
    }

    public function test_index_does_not_estimate_for_all_season_tire(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01'));

        $user = $this->admin();
        $vehicle = $this->createVehicle();
        $this->createTire($vehicle, ['season' => 'all_season', 'status' => 'mounted', 'next_change_date' => null]);

        $response = $this->actingAs($user)->get(route('admin.tires.index'));

        $response->assertOk();
        $response->assertDontSee(__('stimato'));
    }

    public function test_show_page_displays_estimated_next_change(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01'));

        $user = $this->admin();
        $vehicle = $this->createVehicle();
        $tire = $this->createTire($vehicle, ['season' => 'winter', 'status' => 'mounted', 'next_change_date' => null]);

        $response = $this->actingAs($user)->get(route('admin.tires.show', $tire));

        $response->assertOk();
        $response->assertSee('15/04/2027');
        $response->assertSee(__('stimato'));
    }
}
