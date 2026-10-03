<?php

namespace Tests\Feature\Crud;

use App\Models\Equipment;
use App\Models\EquipmentIssue;
use App\Models\EquipmentMaintenanceRecord;
use App\Models\EquipmentType;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentMaintenanceRecordCrudTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    private function createProvider(): Provider
    {
        return Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);
    }

    private function createEquipment(string $name = 'Estintore test'): Equipment
    {
        $type = EquipmentType::create(['name' => 'Estintore ' . uniqid()]);

        return Equipment::create([
            'equipment_type_id' => $type->id,
            'name' => $name,
            'serial_number' => 'SN-' . uniqid(),
        ]);
    }

    public function test_index_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.equipment-maintenance-records.index'));

        $response->assertStatus(200);
    }

    public function test_create_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.equipment-maintenance-records.create'));

        $response->assertStatus(200);
    }

    public function test_show_page_is_reachable(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $issue = EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Guasto',
            'status' => 'open',
        ]);
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today(),
        ]);
        $record->equipments()->attach($equipment->id);
        $issue->update(['equipment_maintenance_record_id' => $record->id]);

        $response = $this->actingAs($user)->get(route('admin.equipment-maintenance-records.show', $record));

        $response->assertStatus(200);
    }

    public function test_edit_page_is_reachable(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today(),
        ]);
        $record->equipments()->attach($equipment->id);

        $response = $this->actingAs($user)->get(route('admin.equipment-maintenance-records.edit', $record));

        $response->assertStatus(200);
    }

    public function test_can_be_stored_with_multiple_equipments(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipmentA = $this->createEquipment('Estintore A');
        $equipmentB = $this->createEquipment('Estintore B');

        $response = $this->actingAs($user)->post(route('admin.equipment-maintenance-records.store'), [
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-15',
            'activity_type' => EquipmentMaintenanceRecord::ACTIVITY_REVISIONE,
            'equipment_ids' => [$equipmentA->id, $equipmentB->id],
        ]);

        $record = EquipmentMaintenanceRecord::first();
        $response->assertRedirect(route('admin.equipment-maintenance-records.show', $record));
        $this->assertCount(2, $record->equipments);
        $this->assertTrue($record->equipments->pluck('id')->contains($equipmentA->id));
        $this->assertTrue($record->equipments->pluck('id')->contains($equipmentB->id));
    }

    public function test_store_requires_at_least_one_equipment(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();

        $response = $this->actingAs($user)->post(route('admin.equipment-maintenance-records.store'), [
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-15',
        ]);

        $response->assertSessionHasErrors(['equipment_ids']);
        $this->assertDatabaseCount('equipment_maintenance_records', 0);
    }

    public function test_linking_open_issue_moves_it_to_in_progress(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $issue = EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Non si accende',
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->post(route('admin.equipment-maintenance-records.store'), [
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-15',
            'equipment_ids' => [$equipment->id],
            'issue_ids' => [$issue->id],
        ]);

        $record = EquipmentMaintenanceRecord::first();
        $response->assertRedirect();
        $issue->refresh();
        $this->assertSame($record->id, $issue->equipment_maintenance_record_id);
        $this->assertSame('in_progress', $issue->status);
    }

    public function test_rejects_issue_not_belonging_to_selected_equipment(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipmentA = $this->createEquipment('A');
        $equipmentB = $this->createEquipment('B');
        $issueOfB = EquipmentIssue::create([
            'equipment_id' => $equipmentB->id,
            'description' => 'Guasto B',
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->post(route('admin.equipment-maintenance-records.store'), [
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-15',
            'equipment_ids' => [$equipmentA->id],
            'issue_ids' => [$issueOfB->id],
        ]);

        $response->assertSessionHasErrors(['issue_ids']);
    }

    public function test_complete_closes_linked_issues_when_resolved(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $issue = EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Non si accende',
            'status' => 'open',
        ]);
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);
        $record->equipments()->attach($equipment->id);
        $issue->update(['equipment_maintenance_record_id' => $record->id, 'status' => 'in_progress']);

        $response = $this->actingAs($user)->patch(route('admin.equipment-maintenance-records.complete', $record), [
            'issue_resolved' => '1',
        ]);

        $response->assertRedirect();
        $record->refresh();
        $issue->refresh();
        $this->assertNotNull($record->return_date);
        $this->assertSame('closed', $issue->status);
    }

    public function test_complete_reopens_linked_issues_when_not_resolved(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $issue = EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Non si accende',
            'status' => 'open',
        ]);
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);
        $record->equipments()->attach($equipment->id);
        $issue->update(['equipment_maintenance_record_id' => $record->id, 'status' => 'in_progress']);

        $this->actingAs($user)->patch(route('admin.equipment-maintenance-records.complete', $record), [
            'issue_resolved' => '0',
        ]);

        $this->assertSame('in_progress', $issue->fresh()->status);
    }

    public function test_complete_does_not_require_issue_resolved_when_no_issues_linked(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);
        $record->equipments()->attach($equipment->id);

        $response = $this->actingAs($user)->patch(route('admin.equipment-maintenance-records.complete', $record), []);

        $response->assertRedirect();
        $this->assertNotNull($record->fresh()->return_date);
    }

    public function test_removing_equipment_on_update_unlinks_its_issue(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipmentA = $this->createEquipment('A');
        $equipmentB = $this->createEquipment('B');
        $issueA = EquipmentIssue::create([
            'equipment_id' => $equipmentA->id,
            'description' => 'Guasto A',
            'status' => 'open',
        ]);
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today(),
        ]);
        $record->equipments()->attach([$equipmentA->id, $equipmentB->id]);
        $issueA->update(['equipment_maintenance_record_id' => $record->id, 'status' => 'in_progress']);

        $response = $this->actingAs($user)->put(route('admin.equipment-maintenance-records.update', $record), [
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-15',
            'equipment_ids' => [$equipmentB->id],
        ]);

        $response->assertRedirect();
        $issueA->refresh();
        $this->assertNull($issueA->equipment_maintenance_record_id);
        $this->assertSame('open', $issueA->status);
    }

    public function test_destroy_reverts_linked_issues_to_open(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $issue = EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Guasto',
            'status' => 'open',
        ]);
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today(),
        ]);
        $record->equipments()->attach($equipment->id);
        $issue->update(['equipment_maintenance_record_id' => $record->id, 'status' => 'in_progress']);

        $response = $this->actingAs($user)->delete(route('admin.equipment-maintenance-records.destroy', $record), ['back' => '/']);

        $response->assertRedirect();
        $this->assertSoftDeleted($record);
        $issue->refresh();
        $this->assertNull($issue->equipment_maintenance_record_id);
        $this->assertSame('open', $issue->status);
    }
}
