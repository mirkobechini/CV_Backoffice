<?php

namespace Tests\Feature;

use App\Models\Equipment;
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
 * Audit sicurezza 2026-11-25 (A2): un EquipmentMaintenanceRecord con
 * attrezzature di PIÙ gruppi reali diversi è "ambiguo" per
 * getVehicleAttribute() (torna null, stesso valore del caso "nessuna
 * attrezzatura assegnata"). HasGroupScopedAccess trattava entrambi i casi
 * come permesso universale — qui si verifica che resti permissivo solo nel
 * primo caso, negato nel secondo a chi non appartiene a nessuno dei gruppi
 * coinvolti.
 */
class EquipmentMaintenanceRecordGroupScopingTest extends TestCase
{
    use RefreshDatabase;

    private function group(string $name, string $inviteCode): Group
    {
        return Group::create(['name' => $name, 'invite_code' => $inviteCode]);
    }

    private function provider(): Provider
    {
        return Provider::create(['name' => 'Officina Test '.uniqid(), 'type' => 'Meccanico']);
    }

    private function capoOf(Group $group): User
    {
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);

        return $user;
    }

    private function equipmentOnVehicleOf(Group $group): Equipment
    {
        $vehicleType = VehicleType::firstOrCreate(['name' => 'Ambulanza'], ['first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $vehicle = Vehicle::create([
            'license_plate' => 'AA'.random_int(100, 999).'AA',
            'internal_code' => (string) random_int(1000, 9999),
            'vehicle_type_id' => $vehicleType->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group->id,
        ]);
        $type = EquipmentType::create(['name' => 'Estintore '.uniqid()]);

        return Equipment::create([
            'equipment_type_id' => $type->id,
            'vehicle_id' => $vehicle->id,
            'name' => 'Estintore',
        ]);
    }

    public function test_capo_of_either_involved_group_can_update_an_ambiguous_record(): void
    {
        $groupA = $this->group('Gruppo A', 'AAAA1111');
        $groupB = $this->group('Gruppo B', 'BBBB2222');
        $equipmentA = $this->equipmentOnVehicleOf($groupA);
        $equipmentB = $this->equipmentOnVehicleOf($groupB);
        $record = EquipmentMaintenanceRecord::create(['provider_id' => $this->provider()->id, 'appointment_date' => today()]);
        $record->equipments()->attach([$equipmentA->id, $equipmentB->id]);

        // Record ambiguo per davvero: getVehicleAttribute() torna null
        // perché coinvolge due veicoli di due gruppi diversi, non perché
        // nessuna attrezzatura è assegnata.
        $this->assertNull($record->vehicle);

        $capoA = $this->capoOf($groupA);
        $capoB = $this->capoOf($groupB);

        $this->assertTrue($capoA->can('update', $record));
        $this->assertTrue($capoB->can('update', $record));
    }

    public function test_capo_of_an_unrelated_group_cannot_update_an_ambiguous_record(): void
    {
        $groupA = $this->group('Gruppo A', 'CCCC3333');
        $groupB = $this->group('Gruppo B', 'DDDD4444');
        $groupC = $this->group('Gruppo C', 'EEEE5555');
        $equipmentA = $this->equipmentOnVehicleOf($groupA);
        $equipmentB = $this->equipmentOnVehicleOf($groupB);
        $record = EquipmentMaintenanceRecord::create(['provider_id' => $this->provider()->id, 'appointment_date' => today()]);
        $record->equipments()->attach([$equipmentA->id, $equipmentB->id]);

        $capoC = $this->capoOf($groupC);

        $this->assertFalse($capoC->can('update', $record));
        $this->assertFalse($capoC->can('delete', $record));
    }

    public function test_any_capo_can_still_update_a_record_with_no_equipment_assigned_to_a_vehicle(): void
    {
        // Regressione: il caso "nessuna attrezzatura assegnata" (non
        // ambiguo, semplicemente senza gruppo) deve restare permissivo
        // come prima di questo fix.
        $group = $this->group('Gruppo Qualunque', 'FFFF6666');
        $type = EquipmentType::create(['name' => 'Estintore '.uniqid()]);
        $equipment = Equipment::create(['equipment_type_id' => $type->id, 'name' => 'Estintore magazzino']);
        $record = EquipmentMaintenanceRecord::create(['provider_id' => $this->provider()->id, 'appointment_date' => today()]);
        $record->equipments()->attach($equipment->id);

        $capo = $this->capoOf($group);

        $this->assertTrue($capo->can('update', $record));
    }
}
