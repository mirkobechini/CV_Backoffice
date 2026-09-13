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

    // Le liste (Vehicle::forCurrentUser()) erano già filtrate per gruppo,
    // ma l'autorizzazione sul singolo record no: prima di questo fix un
    // utente autenticato in un gruppo poteva vedere, e se capo/sottocapo
    // anche modificare o eliminare, il veicolo di un altro gruppo aprendo
    // direttamente la sua show/edit/update/destroy conoscendone l'id.

    public function test_capo_cannot_view_another_groups_vehicle(): void
    {
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);
        $userA = User::factory()->create();
        $groupA->addUser($userA, Group::ROLE_CAPO);
        $vehicleB = $this->vehicle('EF456GH', '0002', $groupB);

        $response = $this->actingAs($userA)->get(route('admin.vehicles.show', $vehicleB));

        $response->assertForbidden();
    }

    public function test_capo_cannot_update_another_groups_vehicle(): void
    {
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);
        $userA = User::factory()->create();
        $groupA->addUser($userA, Group::ROLE_CAPO);
        $vehicleB = $this->vehicle('EF456GH', '0002', $groupB);

        $response = $this->actingAs($userA)->put(route('admin.vehicles.update', $vehicleB), [
            'internal_code' => '9999',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('vehicles', ['id' => $vehicleB->id, 'internal_code' => '0002']);
    }

    public function test_capo_cannot_delete_another_groups_vehicle(): void
    {
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);
        $userA = User::factory()->create();
        $groupA->addUser($userA, Group::ROLE_CAPO);
        $vehicleB = $this->vehicle('EF456GH', '0002', $groupB);

        $response = $this->actingAs($userA)->delete(route('admin.vehicles.destroy', $vehicleB));

        $response->assertForbidden();
        $this->assertDatabaseHas('vehicles', ['id' => $vehicleB->id]);
    }

    public function test_member_can_still_view_own_groups_vehicle(): void
    {
        // Il fix riguarda solo l'isolamento tra gruppi: dentro il proprio
        // gruppo un membro base deve continuare a poter vedere (non
        // modificare) i veicoli, come da HasGroupScopedAccess::view().
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $member = User::factory()->create();
        $group->addUser($member, Group::ROLE_MEMBER);
        $vehicle = $this->vehicle('AB123CD', '0001', $group);

        $response = $this->actingAs($member)->get(route('admin.vehicles.show', $vehicle));

        $response->assertOk();
    }

    public function test_issue_of_another_groups_vehicle_is_not_accessible(): void
    {
        // Stessa falla, verificata su un modello figlio (Issue) invece
        // che sul veicolo stesso: HasGroupScopedAccess risale al gruppo
        // tramite issue->vehicle->group_id.
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);
        $userA = User::factory()->create();
        $groupA->addUser($userA, Group::ROLE_CAPO);
        $vehicleB = $this->vehicle('EF456GH', '0002', $groupB);
        $issue = \App\Models\Issue::create([
            'vehicle_id' => $vehicleB->id,
            'description' => 'Guasto di un altro gruppo',
            'event_date' => '2025-01-01',
        ]);

        $response = $this->actingAs($userA)->get(route('admin.issues.show', $issue));

        $response->assertForbidden();
    }

    public function test_issues_index_excludes_another_groups_issues(): void
    {
        // Prima IssueController::index() non filtrava affatto per gruppo:
        // l'elenco guasti mostrava anche quelli di altri gruppi.
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);
        $userA = User::factory()->create();
        $groupA->addUser($userA, Group::ROLE_CAPO);
        $vehicleA = $this->vehicle('AB123CD', '0001', $groupA);
        $vehicleB = $this->vehicle('EF456GH', '0002', $groupB);
        \App\Models\Issue::create(['vehicle_id' => $vehicleA->id, 'description' => 'Guasto gruppo A', 'event_date' => '2025-01-01']);
        \App\Models\Issue::create(['vehicle_id' => $vehicleB->id, 'description' => 'Guasto gruppo B', 'event_date' => '2025-01-01']);

        $response = $this->actingAs($userA)->get(route('admin.issues.index'));

        $response->assertOk();
        $response->assertSee('Guasto gruppo A');
        $response->assertDontSee('Guasto gruppo B');
    }
}
