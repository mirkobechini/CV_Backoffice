<?php

namespace Tests\Feature\Crud;

use App\Models\Equipment;
use App\Models\EquipmentIssue;
use App\Models\EquipmentMaintenanceRecord;
use App\Models\EquipmentType;
use App\Models\Group;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test del flusso di completamento di EquipmentMaintenanceRecordController,
 * estratti da EquipmentMaintenanceRecordCrudTest.php per non superare la
 * soglia dimensionale di ~500 righe (AGENTS.md) — quel file era già oltre
 * soglia (586 righe) prima di questa estrazione (audit qualità 2026-11-25, F3).
 */
class EquipmentMaintenanceRecordCompletionTest extends TestCase
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
            'resolved_issues' => [$issue->id => '1'],
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
            'resolved_issues' => [$issue->id => '0'],
        ]);

        $this->assertSame('in_progress', $issue->fresh()->status);
    }

    public function test_complete_resolves_each_linked_issue_independently(): void
    {
        // Audit funzionale 2026-11-25 (B1): un unico sì/no per TUTTI i
        // guasti collegati era lo stesso bug già corretto sul lato veicolo
        // nell'audit 2026-10-19 — qui si verifica che due guasti diversi
        // sullo stesso appuntamento possano avere esiti diversi.
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $fixed = EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Manometro scarico',
            'status' => 'open',
        ]);
        $stillBroken = EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Tubo rotto',
            'status' => 'open',
        ]);
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);
        $record->equipments()->attach($equipment->id);
        $fixed->update(['equipment_maintenance_record_id' => $record->id, 'status' => 'in_progress']);
        $stillBroken->update(['equipment_maintenance_record_id' => $record->id, 'status' => 'in_progress']);

        $this->actingAs($user)->patch(route('admin.equipment-maintenance-records.complete', $record), [
            'resolved_issues' => [$fixed->id => '1', $stillBroken->id => '0'],
        ]);

        $this->assertSame('closed', $fixed->fresh()->status);
        $this->assertSame('in_progress', $stillBroken->fresh()->status);
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

    public function test_complete_rejects_an_appointment_whose_date_has_not_arrived_yet(): void
    {
        // Audit funzionale 2026-11-25 (D1): stesso controllo già presente
        // sul lato veicolo, mancante qui — si poteva completare un
        // appuntamento futuro con date incoerenti.
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today()->addDay(),
        ]);
        $record->equipments()->attach($equipment->id);

        $response = $this->actingAs($user)->patch(route('admin.equipment-maintenance-records.complete', $record), []);

        $response->assertSessionHasErrors(['completion']);
        $this->assertNull($record->fresh()->return_date);
    }

    public function test_complete_allows_an_appointment_already_with_a_return_date_even_if_appointment_is_future(): void
    {
        // Un rientro retroattivo compilato esplicitamente (return_date già
        // presente) non deve essere bloccato dal controllo sulla data
        // futura: quel controllo serve solo quando return_date manca
        // ancora, stesso comportamento del lato veicolo.
        $user = $this->createUser();
        $provider = $this->createProvider();
        $equipment = $this->createEquipment();
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today()->addDay(),
            'return_date' => today(),
        ]);
        $record->equipments()->attach($equipment->id);

        $response = $this->actingAs($user)->patch(route('admin.equipment-maintenance-records.complete', $record), []);

        $response->assertRedirect(route('admin.equipment-maintenance-records.show', $record->id));
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

    public function test_show_page_lists_equipment_name_serial_and_assigned_vehicle_in_the_completion_picker(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();
        $vehicleType = VehicleType::create(['name' => 'Ambulanza', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $group = Group::firstOrCreate(['name' => 'Associazione di default'], ['invite_code' => Group::generateInviteCode()]);
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => 'AMB-1',
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group->id,
        ]);
        $type = EquipmentType::create(['name' => 'Estintore '.uniqid()]);
        $onVehicle = Equipment::create([
            'equipment_type_id' => $type->id,
            'vehicle_id' => $vehicle->id,
            'name' => 'Estintore cabina',
            'serial_number' => 'EX-999',
        ]);
        $unassigned = $this->createEquipment('Estintore magazzino');
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => EquipmentMaintenanceRecord::ACTIVITY_REVISIONE,
        ]);
        $record->equipments()->attach([$onVehicle->id, $unassigned->id]);

        $response = $this->actingAs($user)->get(route('admin.equipment-maintenance-records.show', $record));

        $response->assertOk();
        $response->assertSee('Estintore cabina');
        $response->assertSee('EX-999');
        $response->assertSee('AMB-1');
        $response->assertSee('Estintore magazzino');
    }
}
