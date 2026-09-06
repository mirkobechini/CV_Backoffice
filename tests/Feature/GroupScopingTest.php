<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupScopingTest extends TestCase
{
    use RefreshDatabase;

    private function vehicle(string $plate, string $code, ?Group $group = null): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat ' . $code]);
        $model = CarModel::create(['name' => 'Ducato ' . $code, 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza ' . $code, 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);

        return Vehicle::create([
            'license_plate' => $plate,
            'internal_code' => $code,
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group?->id,
        ]);
    }

    public function test_user_only_sees_vehicles_of_own_group(): void
    {
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);

        $userA = User::factory()->create();
        $groupA->addUser($userA, Group::ROLE_CAPO);

        $vehicleA = $this->vehicle('AB123CD', '0001', $groupA);
        $vehicleB = $this->vehicle('EF456GH', '0002', $groupB);

        $this->actingAs($userA);

        $visible = Vehicle::forCurrentUser()->get();

        $this->assertTrue($visible->contains('id', $vehicleA->id));
        $this->assertFalse($visible->contains('id', $vehicleB->id));
    }

    public function test_user_without_group_sees_all_vehicles(): void
    {
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);

        $user = User::factory()->create(); // nessun gruppo

        $vehicleA = $this->vehicle('AB123CD', '0001', $groupA);
        $vehicleB = $this->vehicle('EF456GH', '0002', $groupB);

        $this->actingAs($user);

        $visible = Vehicle::forCurrentUser()->get();

        $this->assertCount(2, $visible);
    }

    public function test_vehicle_creation_assigns_current_user_group(): void
    {
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);

        $this->actingAs($user);

        $brand = Brand::create(['name' => 'Fiat Creazione']);
        $model = CarModel::create(['name' => 'Ducato Creazione', 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza Creazione', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);

        $response = $this->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => '2024-01-01',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vehicles', [
            'license_plate' => 'AB123CD',
            'group_id' => $group->id,
        ]);
    }
}
