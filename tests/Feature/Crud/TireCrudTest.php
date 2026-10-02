<?php

namespace Tests\Feature\Crud;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\Issue;
use App\Models\Tire;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TireCrudTest extends TestCase
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

    private function createVehicle(array $overrides = []): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat ' . uniqid()]);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza ' . uniqid(), 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);

        return Vehicle::create(array_merge([
            'group_id' => $this->group?->id,
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => '2024-01-01',
        ], $overrides));
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

    public function test_tire_index_page_is_reachable(): void
    {
        $user = $this->admin();

        $response = $this->actingAs($user)->get(route('admin.tires.index'));

        $response->assertStatus(200);
    }

    public function test_tire_create_page_is_reachable(): void
    {
        $user = $this->admin();

        $response = $this->actingAs($user)->get(route('admin.tires.create'));

        $response->assertStatus(200);
    }

    public function test_tire_show_page_is_reachable(): void
    {
        $user = $this->admin();
        $vehicle = $this->createVehicle();
        $tire = $this->createTire($vehicle);

        $response = $this->actingAs($user)->get(route('admin.tires.show', $tire));

        $response->assertStatus(200);
    }

    public function test_tire_edit_page_is_reachable(): void
    {
        $user = $this->admin();
        $vehicle = $this->createVehicle();
        $tire = $this->createTire($vehicle);

        $response = $this->actingAs($user)->get(route('admin.tires.edit', $tire));

        $response->assertStatus(200);
    }

    public function test_create_and_edit_pages_expose_vehicle_allowed_tire_sizes_for_suggestion(): void
    {
        $user = $this->admin();
        $vehicle = $this->createVehicle(['allowed_tire_sizes' => ['225/75R16C', '225/75R16']]);
        $tire = $this->createTire($vehicle);

        $this->actingAs($user)->get(route('admin.tires.create'))
            ->assertStatus(200)
            ->assertSee('data-tire-sizes="[&quot;225\/75R16C&quot;,&quot;225\/75R16&quot;]"', false);

        $this->actingAs($user)->get(route('admin.tires.edit', $tire))
            ->assertStatus(200)
            ->assertSee('data-tire-sizes="[&quot;225\/75R16C&quot;,&quot;225\/75R16&quot;]"', false);
    }

    public function test_tire_can_be_stored(): void
    {
        $user = $this->admin();
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.tires.store'), [
            'vehicle_id' => $vehicle->id,
            'season' => 'summer',
            'position' => 'front_left',
            'brand' => 'Pirelli',
            'model_name' => 'Cinturato',
            'size' => '205/55R16',
            'status' => 'stored',
        ]);

        $tire = Tire::first();

        $response->assertRedirect(route('admin.tires.show', $tire));
        $this->assertDatabaseHas('tires', [
            'vehicle_id' => $vehicle->id,
            'season' => 'summer',
            'brand' => 'Pirelli',
        ]);
    }

    public function test_tire_can_be_updated(): void
    {
        $user = $this->admin();
        $vehicle = $this->createVehicle();
        $tire = $this->createTire($vehicle);

        $response = $this->actingAs($user)->put(route('admin.tires.update', $tire), [
            'vehicle_id' => $vehicle->id,
            'season' => 'winter',
            'position' => 'front_left',
            'brand' => 'Continental',
            'model_name' => 'WinterContact',
            'size' => '205/55R16',
            'status' => 'stored',
        ]);

        $response->assertRedirect(route('admin.tires.show', $tire));
        $this->assertDatabaseHas('tires', [
            'id' => $tire->id,
            'brand' => 'Continental',
        ]);
    }

    public function test_tire_can_be_deleted(): void
    {
        $user = $this->admin();
        $vehicle = $this->createVehicle();
        $tire = $this->createTire($vehicle);

        $response = $this->actingAs($user)->delete(route('admin.tires.destroy', $tire));

        $response->assertRedirect(route('admin.tires.index'));
        $this->assertSoftDeleted('tires', ['id' => $tire->id]);
    }

    public function test_season_is_required(): void
    {
        $user = $this->admin();
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.tires.store'), [
            'vehicle_id' => $vehicle->id,
            'position' => 'front_left',
            'status' => 'stored',
        ]);

        $response->assertSessionHasErrors(['season']);
        $this->assertDatabaseCount('tires', 0);
    }

    public function test_recording_a_tire_change_mounts_new_set_and_stores_previous_one(): void
    {
        $user = $this->admin();
        $vehicle = $this->createVehicle();

        $mountedWinter = $this->createTire($vehicle, ['season' => 'winter', 'status' => 'mounted']);
        $summer = $this->createTire($vehicle, ['season' => 'summer', 'status' => 'stored']);

        $response = $this->actingAs($user)->post(route('admin.tires.record-change', $summer), [
            'changed_date' => '2026-04-15',
            'mileage_at_change' => 50000,
            'previous_disposition' => 'stored',
            'notes' => 'Cambio stagionale',
        ]);

        $response->assertRedirect(route('admin.vehicles.show', $vehicle->id));

        $this->assertDatabaseHas('tires', ['id' => $summer->id, 'status' => 'mounted']);
        $this->assertDatabaseHas('tires', ['id' => $mountedWinter->id, 'status' => 'stored']);
        $this->assertDatabaseHas('tire_changes', [
            'vehicle_id' => $vehicle->id,
            'tire_id' => $summer->id,
            'previous_tire_id' => $mountedWinter->id,
            'mileage_at_change' => 50000,
        ]);
    }

    public function test_season_filter_due_shows_only_mounted_tires_of_wrong_season(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-12-01'));

        $user = $this->admin();
        $vehicle = $this->createVehicle();
        $wrongSeason = $this->createTire($vehicle, ['season' => 'summer', 'status' => 'mounted']);
        $this->createTire($vehicle, ['season' => 'winter', 'status' => 'stored']);

        $otherVehicle = $this->createVehicle(['license_plate' => 'XY987ZZ', 'internal_code' => '0002']);
        $this->createTire($otherVehicle, ['season' => 'winter', 'status' => 'mounted']);

        $response = $this->actingAs($user)->get(route('admin.tires.index', ['season_filter' => 'due']));

        $response->assertOk();
        $response->assertSee('Michelin');
        $response->assertViewHas('tires', function ($tires) use ($wrongSeason) {
            return $tires->pluck('id')->all() === [$wrongSeason->id];
        });
    }

    public function test_index_can_filter_by_vehicle(): void
    {
        $user = $this->admin();
        $vehicleA = $this->createVehicle(['license_plate' => 'AB123CD', 'internal_code' => '0001']);
        $vehicleB = $this->createVehicle(['license_plate' => 'XY987ZZ', 'internal_code' => '0002']);
        $tireA = $this->createTire($vehicleA);
        $this->createTire($vehicleB);

        $response = $this->actingAs($user)->get(route('admin.tires.index', ['vehicle_id' => $vehicleA->id]));

        $response->assertOk();
        $response->assertViewHas('tires', fn ($tires) => $tires->pluck('id')->all() === [$tireA->id]);
    }

    public function test_index_can_filter_by_season(): void
    {
        $user = $this->admin();
        $vehicle = $this->createVehicle();
        $winter = $this->createTire($vehicle, ['season' => 'winter']);
        $this->createTire($vehicle, ['season' => 'summer']);

        $response = $this->actingAs($user)->get(route('admin.tires.index', ['season_filter' => 'winter']));

        $response->assertOk();
        $response->assertViewHas('tires', fn ($tires) => $tires->pluck('id')->all() === [$winter->id]);
    }

    public function test_index_can_sort_by_vehicle(): void
    {
        $user = $this->admin();
        $vehicleA = $this->createVehicle(['license_plate' => 'AB123CD', 'internal_code' => '0002']);
        $vehicleB = $this->createVehicle(['license_plate' => 'XY987ZZ', 'internal_code' => '0001']);
        $tireA = $this->createTire($vehicleA);
        $tireB = $this->createTire($vehicleB);

        $response = $this->actingAs($user)->get(route('admin.tires.index', ['sort_by' => 'vehicle', 'sort_dir' => 'asc']));

        $response->assertOk();
        $response->assertViewHas('tires', fn ($tires) => $tires->pluck('id')->all() === [$tireB->id, $tireA->id]);
    }

    public function test_bulk_update_applies_brand_model_and_size_to_selected_tires_only(): void
    {
        $user = $this->admin();
        $vehicle = $this->createVehicle();
        $tireA = $this->createTire($vehicle, ['brand' => null, 'model_name' => null, 'size' => null]);
        $tireB = $this->createTire($vehicle, ['brand' => null, 'model_name' => null, 'size' => null]);
        $untouched = $this->createTire($vehicle, ['brand' => 'Pirelli', 'model_name' => 'Cinturato', 'size' => '195/65R15']);

        $response = $this->actingAs($user)->patch(route('admin.tires.bulk-update'), [
            'tire_ids' => [$tireA->id, $tireB->id],
            'brand' => 'Michelin',
            'model_name' => 'Agilis',
            'size' => '225/75r16c',
        ]);

        $response->assertRedirect(route('admin.tires.index'));

        foreach ([$tireA, $tireB] as $tire) {
            $tire->refresh();
            $this->assertSame('Michelin', $tire->brand);
            $this->assertSame('Agilis', $tire->model_name);
            $this->assertSame('225/75R16C', $tire->size);
        }

        $this->assertSame('Pirelli', $untouched->fresh()->brand);
    }

    public function test_bulk_update_leaves_blank_fields_unchanged(): void
    {
        $user = $this->admin();
        $vehicle = $this->createVehicle();
        $tire = $this->createTire($vehicle, ['brand' => 'Pirelli', 'model_name' => 'Cinturato', 'size' => '195/65R15']);

        $this->actingAs($user)->patch(route('admin.tires.bulk-update'), [
            'tire_ids' => [$tire->id],
            'brand' => 'Michelin',
            // model_name e size non compilati: devono restare quelli originali
        ]);

        $tire->refresh();
        $this->assertSame('Michelin', $tire->brand);
        $this->assertSame('Cinturato', $tire->model_name);
        $this->assertSame('195/65R15', $tire->size);
    }

    public function test_bulk_update_requires_at_least_one_field(): void
    {
        $user = $this->admin();
        $vehicle = $this->createVehicle();
        $tire = $this->createTire($vehicle);

        $response = $this->actingAs($user)->patch(route('admin.tires.bulk-update'), [
            'tire_ids' => [$tire->id],
        ]);

        $response->assertSessionHasErrors('brand');
    }

    public function test_bulk_update_rejects_tire_from_another_group(): void
    {
        $user = $this->admin();
        $otherGroup = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);
        $otherVehicle = Vehicle::create([
            'group_id' => $otherGroup->id,
            'license_plate' => 'ZZ999ZZ',
            'internal_code' => '0002',
            'brand_id' => Brand::create(['name' => 'Iveco ' . uniqid()])->id,
            'car_model_id' => CarModel::create(['name' => 'Daily', 'brand_id' => Brand::first()->id])->id,
            'vehicle_type_id' => VehicleType::create(['name' => 'Ambulanza ' . uniqid(), 'first_inspection_months' => 48, 'regular_inspection_months' => 24])->id,
            'immatricolation_date' => '2024-01-01',
        ]);
        $otherTire = $this->createTire($otherVehicle);

        $response = $this->actingAs($user)->patch(route('admin.tires.bulk-update'), [
            'tire_ids' => [$otherTire->id],
            'brand' => 'Michelin',
        ]);

        $response->assertSessionHasErrors('tire_ids.0');
    }

    public function test_size_is_normalized_to_uppercase(): void
    {
        $vehicle = $this->createVehicle();
        $tire = $this->createTire($vehicle, ['size' => '195/60r16c 99/97h']);

        $this->assertSame('195/60R16C 99/97H', $tire->fresh()->size);
    }

    public function test_issue_can_be_linked_to_a_tire(): void
    {
        $user = $this->admin();
        $vehicle = $this->createVehicle();
        $tire = $this->createTire($vehicle, ['status' => 'mounted']);

        $response = $this->actingAs($user)->post(route('admin.issues.store'), [
            'vehicle_id' => $vehicle->id,
            'tire_ids' => [$tire->id],
            'description' => 'Forature ricorrenti',
            'event_date' => '2026-05-01',
            'status' => 'open',
        ]);

        $issue = Issue::first();

        $response->assertRedirect(route('admin.issues.show', $issue));
        $this->assertDatabaseHas('issue_tire', [
            'issue_id' => $issue->id,
            'tire_id' => $tire->id,
        ]);
    }
}
