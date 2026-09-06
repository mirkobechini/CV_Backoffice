<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiGroupScopingTest extends TestCase
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

    public function test_api_returns_only_vehicles_of_user_group(): void
    {
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);

        $user = User::factory()->create();
        $groupA->addUser($user, Group::ROLE_CAPO);

        $vehicleA = $this->vehicle('AB123CD', '0001', $groupA);
        $vehicleB = $this->vehicle('EF456GH', '0002', $groupB);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/vehicles');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.internal_code', '0001');
        $response->assertJsonMissing(['internal_code' => '0002']);
    }

    public function test_api_show_returns_vehicle_of_own_group(): void
    {
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $groupA->addUser($user, Group::ROLE_CAPO);

        $vehicle = $this->vehicle('AB123CD', '0001', $groupA);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson("/api/vehicles/{$vehicle->id}");

        $response->assertOk();
        $response->assertJsonPath('internal_code', '0001');
    }
}
