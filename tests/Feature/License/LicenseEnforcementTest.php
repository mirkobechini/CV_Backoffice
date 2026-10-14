<?php

namespace Tests\Feature\License;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\License;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function vehicleDependencies(): array
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $vehicleType = VehicleType::create(['name' => 'Ambulanza', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);

        return compact('brand', 'carModel', 'vehicleType');
    }

    private function assignLicense(Group $group, ?int $maxVehicles = null, ?int $maxUsers = null): License
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        return $group->licenses()->create([
            'tier' => License::TIER_FREE,
            'max_vehicles' => $maxVehicles,
            'max_users' => $maxUsers,
            'features' => [],
            'starts_at' => now(),
            'created_by' => $superadmin->id,
        ]);
    }

    public function test_vehicle_creation_is_blocked_at_license_limit(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);
        $this->assignLicense($group, maxVehicles: 1);

        $deps = $this->vehicleDependencies();
        Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $deps['brand']->id,
            'car_model_id' => $deps['carModel']->id,
            'vehicle_type_id' => $deps['vehicleType']->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group->id,
        ]);

        $response = $this->actingAs($capo)->post(route('admin.vehicles.store'), [
            'license_plate' => 'EF456GH',
            'vehicle_type_id' => $deps['vehicleType']->id,
            'internal_code' => '0002',
            'brand_id' => $deps['brand']->id,
            'car_model_id' => $deps['carModel']->id,
            'immatricolation_date' => '2024-01-01',
            'has_warranty_extension' => 0,
        ]);

        $response->assertSessionHasErrors('license');
        $this->assertDatabaseMissing('vehicles', ['license_plate' => 'EF456GH']);
    }

    public function test_vehicle_creation_allowed_within_license_limit(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);
        $this->assignLicense($group, maxVehicles: 3);

        $deps = $this->vehicleDependencies();

        $response = $this->actingAs($capo)->post(route('admin.vehicles.store'), [
            'license_plate' => 'EF456GH',
            'vehicle_type_id' => $deps['vehicleType']->id,
            'internal_code' => '0002',
            'brand_id' => $deps['brand']->id,
            'car_model_id' => $deps['carModel']->id,
            'immatricolation_date' => '2024-01-01',
            'has_warranty_extension' => 0,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('vehicles', ['license_plate' => 'EF456GH']);
    }

    public function test_vehicle_creation_unlimited_without_any_license(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);

        $deps = $this->vehicleDependencies();

        $response = $this->actingAs($capo)->post(route('admin.vehicles.store'), [
            'license_plate' => 'EF456GH',
            'vehicle_type_id' => $deps['vehicleType']->id,
            'internal_code' => '0002',
            'brand_id' => $deps['brand']->id,
            'car_model_id' => $deps['carModel']->id,
            'immatricolation_date' => '2024-01-01',
            'has_warranty_extension' => 0,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('vehicles', ['license_plate' => 'EF456GH']);
    }

    public function test_join_group_is_blocked_at_user_license_limit(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);
        $this->assignLicense($group, maxUsers: 1);

        $joiner = User::factory()->create();

        $response = $this->actingAs($joiner)->post(route('admin.groups.join'), [
            'invite_code' => 'AAAA1111',
        ]);

        $response->assertSessionHasErrors('invite_code');
        $this->assertNull($joiner->fresh()->roleIn($group));
    }

    public function test_create_user_in_group_is_blocked_at_user_license_limit(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);
        $this->assignLicense($group, maxUsers: 1);

        $response = $this->actingAs($capo)->post(route('admin.groups.users.store', $group), [
            'name' => 'Nuovo Utente',
            'email' => 'nuovo@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'member',
        ]);

        $response->assertSessionHasErrors('license');
        $this->assertDatabaseMissing('users', ['email' => 'nuovo@example.com']);
    }
}
