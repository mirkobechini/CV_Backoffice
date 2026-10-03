<?php

namespace Tests\Feature\Crud;

use App\Models\Equipment;
use App\Models\EquipmentIssue;
use App\Models\EquipmentType;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentIssueCrudTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    private function createEquipment(): Equipment
    {
        $type = EquipmentType::create(['name' => 'Estintore ' . uniqid()]);

        return Equipment::create([
            'equipment_type_id' => $type->id,
            'name' => 'Estintore test',
            'serial_number' => 'SN-' . uniqid(),
        ]);
    }

    private function createEquipmentIssue(): array
    {
        $equipment = $this->createEquipment();
        $issue = EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Non si accende',
            'status' => 'open',
            'event_date' => '2026-01-01',
        ]);

        return compact('equipment', 'issue');
    }

    public function test_index_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.equipment-issues.index'));

        $response->assertStatus(200);
    }

    public function test_create_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.equipment-issues.create'));

        $response->assertStatus(200);
    }

    public function test_show_page_is_reachable(): void
    {
        $user = $this->createUser();
        $issue = $this->createEquipmentIssue()['issue'];

        $response = $this->actingAs($user)->get(route('admin.equipment-issues.show', $issue));

        $response->assertStatus(200);
    }

    public function test_edit_page_is_reachable(): void
    {
        $user = $this->createUser();
        $issue = $this->createEquipmentIssue()['issue'];

        $response = $this->actingAs($user)->get(route('admin.equipment-issues.edit', $issue));

        $response->assertStatus(200);
    }

    public function test_can_be_stored(): void
    {
        $user = $this->createUser();
        $equipment = $this->createEquipment();

        $response = $this->actingAs($user)->post(route('admin.equipment-issues.store'), [
            'equipment_id' => $equipment->id,
            'description' => 'Manometro scarico',
            'status' => 'open',
            'event_date' => '2026-01-15',
        ]);

        $issue = EquipmentIssue::first();
        $response->assertRedirect(route('admin.equipment-issues.show', $issue));
        $this->assertDatabaseHas('equipment_issues', [
            'equipment_id' => $equipment->id,
            'description' => 'Manometro scarico',
            'status' => 'open',
        ]);
    }

    public function test_can_be_updated(): void
    {
        $user = $this->createUser();
        $data = $this->createEquipmentIssue();

        $response = $this->actingAs($user)->put(route('admin.equipment-issues.update', $data['issue']), [
            'equipment_id' => $data['equipment']->id,
            'description' => 'Risolto in loco',
            'status' => 'closed',
            'event_date' => '2026-01-01',
        ]);

        $response->assertRedirect(route('admin.equipment-issues.show', $data['issue']));
        $this->assertDatabaseHas('equipment_issues', [
            'id' => $data['issue']->id,
            'description' => 'Risolto in loco',
            'status' => 'closed',
        ]);
    }

    public function test_can_be_deleted(): void
    {
        $user = $this->createUser();
        $issue = $this->createEquipmentIssue()['issue'];

        $response = $this->actingAs($user)->delete(route('admin.equipment-issues.destroy', $issue));

        $response->assertRedirect(route('admin.equipment-issues.index'));
        $this->assertSoftDeleted($issue);
    }

    public function test_description_is_required(): void
    {
        $user = $this->createUser();
        $equipment = $this->createEquipment();

        $response = $this->actingAs($user)->post(route('admin.equipment-issues.store'), [
            'equipment_id' => $equipment->id,
            'status' => 'open',
            'event_date' => '2026-01-15',
        ]);

        $response->assertSessionHasErrors(['description']);
        $this->assertDatabaseCount('equipment_issues', 0);
    }

    public function test_rejects_equipment_from_another_group(): void
    {
        $user = $this->createUser();
        $otherGroup = Group::create(['name' => 'Altro gruppo', 'invite_code' => Group::generateInviteCode()]);
        $otherOwner = User::factory()->create();
        $otherGroup->addUser($otherOwner, Group::ROLE_CAPO);

        // Attrezzatura assegnata a un veicolo di un altro gruppo.
        $brand = \App\Models\Brand::create(['name' => 'Iveco']);
        $model = \App\Models\CarModel::create(['name' => 'Daily', 'brand_id' => $brand->id]);
        $vehicleType = \App\Models\VehicleType::create(['name' => 'Amb altro', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $otherVehicle = \App\Models\Vehicle::create([
            'license_plate' => 'XY987ZZ',
            'internal_code' => '9999',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $vehicleType->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $otherGroup->id,
        ]);
        $type = EquipmentType::create(['name' => 'Estintore altro']);
        $otherEquipment = Equipment::create([
            'equipment_type_id' => $type->id,
            'vehicle_id' => $otherVehicle->id,
            'name' => 'Estintore altrui',
            'serial_number' => 'SN-OTHER',
        ]);

        $response = $this->actingAs($user)->post(route('admin.equipment-issues.store'), [
            'equipment_id' => $otherEquipment->id,
            'description' => 'Tentativo',
            'status' => 'open',
            'event_date' => '2026-01-15',
        ]);

        $response->assertSessionHasErrors(['equipment_id']);
    }

    public function test_allows_equipment_not_assigned_to_any_vehicle(): void
    {
        // Permissivo come il resto del progetto: attrezzatura senza
        // veicolo assegnato è selezionabile da chiunque.
        $user = $this->createUser();
        $equipment = $this->createEquipment();

        $response = $this->actingAs($user)->post(route('admin.equipment-issues.store'), [
            'equipment_id' => $equipment->id,
            'description' => 'Guasto generico',
            'status' => 'open',
            'event_date' => '2026-01-15',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('equipment_issues', ['equipment_id' => $equipment->id]);
    }
}
