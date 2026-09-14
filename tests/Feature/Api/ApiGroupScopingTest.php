<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Group;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
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
        $brand = Brand::create(['name' => 'Fiat '.$code]);
        $model = CarModel::create(['name' => 'Ducato '.$code, 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza '.$code, 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);

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

    public function test_api_show_returns_404_for_vehicle_of_another_group(): void
    {
        // Prima non c'era alcun controllo: un token valido di un gruppo
        // qualsiasi poteva leggere il veicolo (e guasti/scadenze/
        // attrezzature collegate) di un altro gruppo indovinandone l'id.
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);

        $userA = User::factory()->create();
        $groupA->addUser($userA, Group::ROLE_CAPO);

        $vehicleB = $this->vehicle('EF456GH', '0002', $groupB);

        $token = $userA->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson("/api/vehicles/{$vehicleB->id}");

        $response->assertNotFound();
    }

    public function test_api_issue_show_returns_404_for_another_groups_vehicle(): void
    {
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);
        $userA = User::factory()->create();
        $groupA->addUser($userA, Group::ROLE_CAPO);
        $vehicleB = $this->vehicle('EF456GH', '0002', $groupB);
        $issue = Issue::create(['vehicle_id' => $vehicleB->id, 'description' => 'Guasto', 'event_date' => '2025-01-01']);

        $token = $userA->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson("/api/issues/{$issue->id}");

        $response->assertNotFound();
    }

    public function test_api_deadline_show_returns_404_for_another_groups_vehicle(): void
    {
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);
        $userA = User::factory()->create();
        $groupA->addUser($userA, Group::ROLE_CAPO);
        $vehicleB = $this->vehicle('EF456GH', '0002', $groupB);
        $deadline = Deadline::create(['vehicle_id' => $vehicleB->id, 'type' => Deadline::TYPE_MINISTERIAL, 'due_date' => '2030-06-01']);

        $token = $userA->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson("/api/deadlines/{$deadline->id}");

        $response->assertNotFound();
    }

    public function test_api_maintenance_record_show_returns_404_for_another_groups_vehicle(): void
    {
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);
        $userA = User::factory()->create();
        $groupA->addUser($userA, Group::ROLE_CAPO);
        $vehicleB = $this->vehicle('EF456GH', '0002', $groupB);
        $provider = Provider::create(['name' => 'Officina', 'type' => 'Meccanico']);
        $record = MaintenanceRecord::create(['vehicle_id' => $vehicleB->id, 'provider_id' => $provider->id, 'appointment_date' => '2025-02-01']);

        $token = $userA->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson("/api/maintenance-records/{$record->id}");

        $response->assertNotFound();
    }

    public function test_api_issues_index_excludes_another_groups_issues(): void
    {
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);
        $userA = User::factory()->create();
        $groupA->addUser($userA, Group::ROLE_CAPO);
        $vehicleA = $this->vehicle('AB123CD', '0001', $groupA);
        $vehicleB = $this->vehicle('EF456GH', '0002', $groupB);
        Issue::create(['vehicle_id' => $vehicleA->id, 'description' => 'Guasto A', 'event_date' => '2025-01-01']);
        Issue::create(['vehicle_id' => $vehicleB->id, 'description' => 'Guasto B', 'event_date' => '2025-01-01']);

        $token = $userA->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/issues');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.description', 'Guasto A');
    }
}
