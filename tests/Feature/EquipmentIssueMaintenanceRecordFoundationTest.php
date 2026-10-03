<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
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
 * Blocco A (fondamenta) della feature guasti/appuntamenti attrezzature:
 * nessuna UI/controller ancora, solo migrazioni/modelli/relazioni/policy.
 */
class EquipmentIssueMaintenanceRecordFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function createGroup(string $name): Group
    {
        return Group::create(['name' => $name, 'invite_code' => Group::generateInviteCode()]);
    }

    private function createVehicle(Group $group): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat ' . uniqid()]);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Amb ' . uniqid(), 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);

        return Vehicle::create([
            'group_id' => $group->id,
            'license_plate' => 'AB' . rand(100, 999) . 'CD',
            'internal_code' => (string) rand(1000, 9999),
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => '2020-01-01',
        ]);
    }

    private function createEquipment(?Vehicle $vehicle = null): Equipment
    {
        $type = EquipmentType::create(['name' => 'Estintore ' . uniqid()]);

        return Equipment::create([
            'equipment_type_id' => $type->id,
            'vehicle_id' => $vehicle?->id,
            'name' => 'Estintore test',
            'serial_number' => 'SN-' . uniqid(),
        ]);
    }

    public function test_equipment_issue_relations_resolve_correctly(): void
    {
        $group = $this->createGroup('G');
        $vehicle = $this->createVehicle($group);
        $equipment = $this->createEquipment($vehicle);
        $provider = Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);

        $maintenanceRecord = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today(),
            'activity_type' => EquipmentMaintenanceRecord::ACTIVITY_RIPARAZIONE,
        ]);
        $maintenanceRecord->equipments()->attach($equipment->id);

        $issue = EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'equipment_maintenance_record_id' => $maintenanceRecord->id,
            'description' => 'Non si accende',
            'status' => 'open',
        ]);

        $this->assertTrue($equipment->issues->contains($issue));
        $this->assertTrue($equipment->maintenanceRecords->contains($maintenanceRecord));
        $this->assertTrue($maintenanceRecord->equipments->contains($equipment));
        $this->assertTrue($maintenanceRecord->issues->contains($issue));
        $this->assertSame($equipment->id, $issue->equipment->id);
        $this->assertSame($maintenanceRecord->id, $issue->maintenanceRecord->id);
        $this->assertSame($vehicle->id, $issue->vehicle->id);
        $this->assertTrue($provider->equipmentMaintenanceRecords->contains($maintenanceRecord));
    }

    public function test_equipment_issue_on_unassigned_equipment_has_no_vehicle(): void
    {
        $equipment = $this->createEquipment();
        $issue = EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Guasto generico',
            'status' => 'open',
        ]);

        $this->assertNull($issue->vehicle);
    }

    public function test_capo_cannot_manage_equipment_issue_from_another_group(): void
    {
        $ownGroup = $this->createGroup('Mio gruppo');
        $otherGroup = $this->createGroup('Altro gruppo');

        $user = User::factory()->create();
        $ownGroup->addUser($user, Group::ROLE_CAPO);

        $otherVehicle = $this->createVehicle($otherGroup);
        $otherEquipment = $this->createEquipment($otherVehicle);
        $otherIssue = EquipmentIssue::create([
            'equipment_id' => $otherEquipment->id,
            'description' => 'Guasto altrui',
            'status' => 'open',
        ]);

        $this->assertFalse($user->can('update', $otherIssue));
        $this->assertFalse($user->can('delete', $otherIssue));
        $this->assertFalse($user->can('view', $otherIssue));
    }

    public function test_capo_can_manage_equipment_issue_in_own_group(): void
    {
        $ownGroup = $this->createGroup('Mio gruppo');
        $user = User::factory()->create();
        $ownGroup->addUser($user, Group::ROLE_CAPO);

        $ownVehicle = $this->createVehicle($ownGroup);
        $ownEquipment = $this->createEquipment($ownVehicle);
        $ownIssue = EquipmentIssue::create([
            'equipment_id' => $ownEquipment->id,
            'description' => 'Guasto mio',
            'status' => 'open',
        ]);

        $this->assertTrue($user->can('update', $ownIssue));
        $this->assertTrue($user->can('delete', $ownIssue));
    }

    public function test_capo_can_manage_equipment_maintenance_record_with_no_equipment_attached(): void
    {
        // getVehicleAttribute() torna null quando non c'è un unico veicolo
        // condiviso da verificare (qui: nessuna attrezzatura collegata
        // affatto, caso genuinamente ambiguo): HasGroupScopedAccess lo
        // tratta come permissivo, stesso comportamento dell'attrezzatura
        // senza veicolo assegnato.
        $ownGroup = $this->createGroup('Mio gruppo');
        $user = User::factory()->create();
        $ownGroup->addUser($user, Group::ROLE_CAPO);

        $provider = Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today(),
        ]);

        $this->assertTrue($user->can('update', $record));
        $this->assertTrue($user->can('delete', $record));
    }

    public function test_capo_cannot_manage_equipment_maintenance_record_of_another_group_when_equipment_shares_one_vehicle(): void
    {
        // Caso comune: tutte le attrezzature collegate appartengono allo
        // stesso veicolo (spesso una sola). getVehicleAttribute() lo
        // riconosce e HasGroupScopedAccess applica l'isolamento normale,
        // invece di restare permissivo come prima di questo fix.
        $ownGroup = $this->createGroup('Mio gruppo');
        $otherGroup = $this->createGroup('Altro gruppo');
        $user = User::factory()->create();
        $ownGroup->addUser($user, Group::ROLE_CAPO);

        $otherVehicle = $this->createVehicle($otherGroup);
        $otherEquipment = $this->createEquipment($otherVehicle);
        $provider = Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today(),
        ]);
        $record->equipments()->attach($otherEquipment->id);

        $this->assertFalse($user->can('update', $record));
        $this->assertFalse($user->can('delete', $record));
    }

    public function test_capo_can_manage_equipment_maintenance_record_in_own_group(): void
    {
        $ownGroup = $this->createGroup('Mio gruppo');
        $user = User::factory()->create();
        $ownGroup->addUser($user, Group::ROLE_CAPO);

        $ownVehicle = $this->createVehicle($ownGroup);
        $ownEquipment = $this->createEquipment($ownVehicle);
        $provider = Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today(),
        ]);
        $record->equipments()->attach($ownEquipment->id);

        $this->assertTrue($user->can('update', $record));
        $this->assertTrue($user->can('delete', $record));
    }

    public function test_capo_can_manage_equipment_maintenance_record_spanning_multiple_groups(): void
    {
        // Caso genuinamente ambiguo (attrezzature di gruppi diversi nello
        // stesso appuntamento): nessun singolo gruppo proprietario da
        // verificare, resta permissivo di proposito.
        $ownGroup = $this->createGroup('Mio gruppo');
        $otherGroup = $this->createGroup('Altro gruppo');
        $user = User::factory()->create();
        $ownGroup->addUser($user, Group::ROLE_CAPO);

        $ownVehicle = $this->createVehicle($ownGroup);
        $ownEquipment = $this->createEquipment($ownVehicle);
        $otherVehicle = $this->createVehicle($otherGroup);
        $otherEquipment = $this->createEquipment($otherVehicle);

        $provider = Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today(),
        ]);
        $record->equipments()->attach([$ownEquipment->id, $otherEquipment->id]);

        $this->assertTrue($user->can('update', $record));
        $this->assertTrue($user->can('delete', $record));
    }

    public function test_equipment_issue_soft_delete_and_activity_log(): void
    {
        $equipment = $this->createEquipment();
        $issue = EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Guasto da cancellare',
            'status' => 'open',
        ]);

        $issue->delete();

        $this->assertSoftDeleted($issue);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => EquipmentIssue::class,
            'subject_id' => $issue->id,
            'description' => 'deleted',
        ]);
    }
}
