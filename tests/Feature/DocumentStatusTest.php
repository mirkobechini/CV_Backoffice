<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentStatusTest extends TestCase
{
    use RefreshDatabase;

    private function vehicle(string $plate, string $code, ?Group $group = null, array $overrides = []): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat ' . $code]);
        $model = CarModel::create(['name' => 'Ducato ' . $code, 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza ' . $code, 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);

        return Vehicle::create(array_merge([
            'license_plate' => $plate,
            'internal_code' => $code,
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group?->id,
        ], $overrides));
    }

    public function test_page_is_reachable(): void
    {
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);

        $response = $this->actingAs($user)->get(route('admin.documents.index'));

        $response->assertOk();
    }

    public function test_shows_missing_badge_for_vehicle_without_registration_card(): void
    {
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);
        $vehicle = $this->vehicle('AB123CD', '0001', $group);

        $response = $this->actingAs($user)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertSee($vehicle->internal_code);
        $response->assertSee(__('Mancante'));
    }

    public function test_shows_present_badge_for_vehicle_with_registration_card(): void
    {
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);
        $this->vehicle('AB123CD', '0001', $group, ['registration_card_path' => 'registration_cards/foo.pdf']);

        $response = $this->actingAs($user)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertSee(__('Presente'));
    }

    public function test_shows_deadline_status_badge_for_each_type(): void
    {
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);
        $vehicle = $this->vehicle('AB123CD', '0001', $group);
        Deadline::where('vehicle_id', $vehicle->id)->forceDelete();

        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => now()->subDays(10),
            'status' => Deadline::STATUS_EXPIRED,
            'is_renewed' => false,
        ]);

        $response = $this->actingAs($user)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertSee('Scaduta');
    }

    public function test_excludes_another_groups_vehicles(): void
    {
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);
        $userA = User::factory()->create();
        $groupA->addUser($userA, Group::ROLE_CAPO);
        $this->vehicle('AB123CD', '0001', $groupA);
        $vehicleB = $this->vehicle('EF456GH', '0002', $groupB);

        $response = $this->actingAs($userA)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertDontSee($vehicleB->internal_code);
    }

    public function test_shows_equipment_status_badges(): void
    {
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);
        $vehicle = $this->vehicle('AB123CD', '0001', $group);
        $equipmentType = EquipmentType::create(['name' => 'Estintore']);
        Equipment::create([
            'vehicle_id' => $vehicle->id,
            'equipment_type_id' => $equipmentType->id,
            'name' => 'Estintore cabina',
            'expiration_date' => now()->subDays(5),
        ]);

        $response = $this->actingAs($user)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertSee('Estintore cabina');
        $response->assertSee('Scaduta');
    }
}
