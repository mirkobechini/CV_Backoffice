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

    public function test_destroy_ignores_back_pointing_to_its_own_just_deleted_show_page(): void
    {
        // Audit funzionale 2026-11-25 (D5): stesso controllo già presente
        // lato veicolo, mancante qui — "back" che punta alla show
        // dell'appuntamento appena eliminato darebbe un redirect a 404.
        $user = $this->createUser();
        $provider = $this->createProvider();
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today(),
        ]);

        $response = $this->actingAs($user)->delete(route('admin.equipment-maintenance-records.destroy', $record), [
            'back' => route('admin.equipment-maintenance-records.show', $record),
        ]);

        $response->assertRedirect(route('admin.equipment-maintenance-records.index'));
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

    public function test_store_blocks_a_duplicate_double_submit(): void
    {
        // Audit funzionale 2026-11-25 (D3): stesso controllo già presente
        // lato veicolo, mancante qui — un doppio-click/doppio-submit del
        // form creava due appuntamenti identici.
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $payload = [
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-15',
            'activity_type' => EquipmentMaintenanceRecord::ACTIVITY_REVISIONE,
            'equipment_ids' => [$equipment->id],
        ];

        $first = $this->actingAs($user)->post(route('admin.equipment-maintenance-records.store'), $payload);
        $second = $this->actingAs($user)->post(route('admin.equipment-maintenance-records.store'), $payload);

        $this->assertDatabaseCount('equipment_maintenance_records', 1);
        $record = EquipmentMaintenanceRecord::first();
        $first->assertRedirect(route('admin.equipment-maintenance-records.show', $record));
        $second->assertRedirect(route('admin.equipment-maintenance-records.show', $record));
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

    public function test_store_rejects_overlapping_appointment_for_the_same_equipment(): void
    {
        // Audit funzionale 2026-11-25 (D2): stesso controllo già presente
        // lato veicolo, mancante qui — la stessa attrezzatura era
        // prenotabile in due appuntamenti sovrapposti.
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $existing = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-10',
            'return_date' => '2026-01-20',
        ]);
        $existing->equipments()->attach($equipment->id);

        $response = $this->actingAs($user)->post(route('admin.equipment-maintenance-records.store'), [
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-15',
            'equipment_ids' => [$equipment->id],
        ]);

        $response->assertSessionHasErrors(['equipment_ids']);
        $this->assertDatabaseCount('equipment_maintenance_records', 1);
    }

    public function test_store_allows_non_overlapping_appointment_for_the_same_equipment(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $existing = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-10',
            'return_date' => '2026-01-20',
        ]);
        $existing->equipments()->attach($equipment->id);

        $response = $this->actingAs($user)->post(route('admin.equipment-maintenance-records.store'), [
            'provider_id' => $provider->id,
            'appointment_date' => '2026-02-01',
            'equipment_ids' => [$equipment->id],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('equipment_maintenance_records', 2);
    }

    public function test_update_without_changing_dates_ignores_preexisting_overlap(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $other = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-10',
            'return_date' => '2026-01-20',
        ]);
        $other->equipments()->attach($equipment->id);
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-15',
            'return_date' => '2026-01-25',
        ]);
        $record->equipments()->attach($equipment->id);

        $response = $this->actingAs($user)->put(route('admin.equipment-maintenance-records.update', $record), [
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-15',
            'return_date' => '2026-01-25',
            'equipment_ids' => [$equipment->id],
            'notes' => 'Aggiunta una nota, nessuna data cambiata.',
        ]);

        $response->assertSessionDoesntHaveErrors();
    }

    public function test_update_still_rejects_overlap_when_dates_actually_change(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $other = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => '2026-02-10',
            'return_date' => '2026-02-20',
        ]);
        $other->equipments()->attach($equipment->id);
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-15',
            'return_date' => '2026-01-25',
        ]);
        $record->equipments()->attach($equipment->id);

        $response = $this->actingAs($user)->put(route('admin.equipment-maintenance-records.update', $record), [
            'provider_id' => $provider->id,
            'appointment_date' => '2026-02-15',
            'return_date' => '2026-02-18',
            'equipment_ids' => [$equipment->id],
        ]);

        $response->assertSessionHasErrors(['equipment_ids']);
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
