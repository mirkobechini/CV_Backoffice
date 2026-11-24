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
        $type = EquipmentType::create(['name' => 'Estintore '.uniqid()]);

        return Equipment::create([
            'equipment_type_id' => $type->id,
            'name' => $name,
            'serial_number' => 'SN-'.uniqid(),
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

    public function test_create_page_lists_providers_alphabetically(): void
    {
        $user = $this->createUser();
        Provider::create(['name' => 'Zeta Autofficina', 'type' => 'Meccanico']);
        Provider::create(['name' => 'Alfa Autofficina', 'type' => 'Meccanico']);

        $response = $this->actingAs($user)->get(route('admin.equipment-maintenance-records.create'));

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertTrue(strpos($content, 'Alfa Autofficina') < strpos($content, 'Zeta Autofficina'));
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

    public function test_destroy_ignores_back_pointing_to_an_external_url(): void
    {
        // Audit sicurezza SM4: `back` è un input utente, non va seguito
        // ciecamente — un `back` assoluto esterno reindirizzerebbe fuori
        // dall'app (open redirect).
        $user = $this->createUser();
        $provider = $this->createProvider();
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today(),
        ]);

        $response = $this->actingAs($user)->delete(route('admin.equipment-maintenance-records.destroy', $record), [
            'back' => 'https://evil.example/phishing',
        ]);

        $response->assertRedirect(route('admin.equipment-maintenance-records.index'));
    }

    public function test_destroy_follows_a_safe_relative_back_url(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today(),
        ]);

        $response = $this->actingAs($user)->delete(route('admin.equipment-maintenance-records.destroy', $record), [
            'back' => '/admin/vehicles',
        ]);

        $response->assertRedirect('/admin/vehicles');
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

    public function test_cost_is_optional_and_saved_when_provided(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment('Estintore A');

        // Senza costo: non deve bloccare il salvataggio (campo opzionale).
        $this->actingAs($user)->post(route('admin.equipment-maintenance-records.store'), [
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-15',
            'equipment_ids' => [$equipment->id],
        ])->assertSessionDoesntHaveErrors('cost');

        $record = EquipmentMaintenanceRecord::first();
        $this->assertNull($record->cost);

        $response = $this->actingAs($user)->put(route('admin.equipment-maintenance-records.update', $record), [
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-15',
            'equipment_ids' => [$equipment->id],
            'cost' => '45.50',
        ]);

        $response->assertSessionDoesntHaveErrors('cost');
        $this->assertSame('45.50', $record->fresh()->cost);
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

    public function test_rejects_completed_issue_id_not_belonging_to_the_appointment(): void
    {
        // Audit sicurezza 2026-10-22 (SH6): completed_issue_ids non era
        // validato affatto — un id arbitrario (anche di un'attrezzatura
        // completamente estranea) veniva chiuso senza alcun controllo.
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
            'return_date' => '2026-01-16',
            'equipment_ids' => [$equipmentA->id],
            'completed_issue_ids' => [$issueOfB->id],
        ]);

        $response->assertSessionHasErrors(['completed_issue_ids']);
        $this->assertSame('open', $issueOfB->fresh()->status);
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

    public function test_complete_updates_revision_date_when_activity_is_revisione(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => EquipmentMaintenanceRecord::ACTIVITY_REVISIONE,
        ]);
        $record->equipments()->attach($equipment->id);

        $response = $this->actingAs($user)->patch(route('admin.equipment-maintenance-records.complete', $record), [
            'completed_equipment_ids' => [$equipment->id],
        ]);

        $response->assertRedirect();
        $equipment->refresh();
        $this->assertEquals($record->fresh()->return_date->toDateString(), $equipment->revision_date->toDateString());
        $this->assertSame(1, $equipment->revisions()->count());
    }

    public function test_complete_updates_collaudo_date_when_activity_is_collaudo(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => EquipmentMaintenanceRecord::ACTIVITY_COLLAUDO,
        ]);
        $record->equipments()->attach($equipment->id);

        $this->actingAs($user)->patch(route('admin.equipment-maintenance-records.complete', $record), [
            'completed_equipment_ids' => [$equipment->id],
        ]);

        $equipment->refresh();
        $this->assertEquals($record->fresh()->return_date->toDateString(), $equipment->collaudo_date->toDateString());
    }

    public function test_complete_does_not_update_equipment_dates_for_other_activity_types(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => EquipmentMaintenanceRecord::ACTIVITY_RIPARAZIONE,
        ]);
        $record->equipments()->attach($equipment->id);

        $this->actingAs($user)->patch(route('admin.equipment-maintenance-records.complete', $record), [
            'completed_equipment_ids' => [$equipment->id],
        ]);

        $this->assertNull($equipment->fresh()->revision_date);
    }

    public function test_complete_skips_equipment_not_selected_as_completed(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $done = $this->createEquipment('Fatta');
        $postponed = $this->createEquipment('Rimandata');
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => EquipmentMaintenanceRecord::ACTIVITY_REVISIONE,
        ]);
        $record->equipments()->attach([$done->id, $postponed->id]);

        $this->actingAs($user)->patch(route('admin.equipment-maintenance-records.complete', $record), [
            'completed_equipment_ids' => [$done->id],
        ]);

        $this->assertNotNull($done->fresh()->revision_date);
        $this->assertNull($postponed->fresh()->revision_date);
    }

    public function test_complete_rejects_equipment_id_not_belonging_to_the_appointment(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $linked = $this->createEquipment('Collegata');
        $foreign = $this->createEquipment('Estranea');
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => EquipmentMaintenanceRecord::ACTIVITY_REVISIONE,
        ]);
        $record->equipments()->attach($linked->id);

        $response = $this->actingAs($user)->patch(route('admin.equipment-maintenance-records.complete', $record), [
            'completed_equipment_ids' => [$foreign->id],
        ]);

        $response->assertSessionHasErrors(['completed_equipment_ids']);
        $this->assertNull($foreign->fresh()->revision_date);
        $this->assertNull($record->fresh()->return_date);
    }

    public function test_show_page_defaults_to_completing_the_single_linked_equipment(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => EquipmentMaintenanceRecord::ACTIVITY_REVISIONE,
        ]);
        $record->equipments()->attach($equipment->id);

        $response = $this->actingAs($user)->get(route('admin.equipment-maintenance-records.show', $record));

        $response->assertOk();
        $response->assertSee('name="completed_equipment_ids[]" value="'.$equipment->id.'"', false);
    }

    public function test_show_page_lets_you_pick_which_equipment_was_completed_when_several_are_linked(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $first = $this->createEquipment('Prima');
        $second = $this->createEquipment('Seconda');
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => EquipmentMaintenanceRecord::ACTIVITY_REVISIONE,
        ]);
        $record->equipments()->attach([$first->id, $second->id]);

        $response = $this->actingAs($user)->get(route('admin.equipment-maintenance-records.show', $record));

        $response->assertOk();
        $response->assertSee('Su quali attrezzature è stato effettuato l\'intervento?', false);
        $response->assertSee('type="checkbox" name="completed_equipment_ids[]"', false);
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
