<?php

namespace Tests\Feature;

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
 * Equipment::scopeVisibleToGroup()/scopeVisibleToCurrentUser() e gli
 * equivalenti su EquipmentIssue/EquipmentMaintenanceRecord, estratti dal
 * pattern duplicato 22 volte (audit qualità 2026-11-25, C1). Verifica che
 * il comportamento resti identico a quello inline che sostituiscono:
 * attrezzatura non assegnata visibile a tutti, assegnata visibile solo al
 * gruppo del suo veicolo.
 */
class EquipmentVisibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    private function group(string $name, string $inviteCode): Group
    {
        return Group::create(['name' => $name, 'invite_code' => $inviteCode]);
    }

    private function equipmentOnVehicleOf(?Group $group): Equipment
    {
        $type = EquipmentType::create(['name' => 'Estintore '.uniqid()]);

        if (! $group) {
            return Equipment::create(['equipment_type_id' => $type->id, 'name' => 'Estintore non assegnato']);
        }

        $vehicleType = VehicleType::firstOrCreate(['name' => 'Ambulanza'], ['first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $vehicle = Vehicle::create([
            'license_plate' => 'AA'.random_int(100, 999).'AA',
            'internal_code' => (string) random_int(1000, 9999),
            'vehicle_type_id' => $vehicleType->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group->id,
        ]);

        return Equipment::create(['equipment_type_id' => $type->id, 'vehicle_id' => $vehicle->id, 'name' => 'Estintore']);
    }

    public function test_equipment_visible_to_group_includes_unassigned_and_own_group_only(): void
    {
        $groupA = $this->group('Gruppo A', 'AAAA1111');
        $groupB = $this->group('Gruppo B', 'BBBB2222');
        $unassigned = $this->equipmentOnVehicleOf(null);
        $ownA = $this->equipmentOnVehicleOf($groupA);
        $ownB = $this->equipmentOnVehicleOf($groupB);

        $visibleIds = Equipment::visibleToGroup($groupA->id)->pluck('id');

        $this->assertTrue($visibleIds->contains($unassigned->id));
        $this->assertTrue($visibleIds->contains($ownA->id));
        $this->assertFalse($visibleIds->contains($ownB->id));
    }

    public function test_equipment_issue_visible_to_group_follows_its_equipment(): void
    {
        $groupA = $this->group('Gruppo A', 'CCCC3333');
        $groupB = $this->group('Gruppo B', 'DDDD4444');
        $equipmentA = $this->equipmentOnVehicleOf($groupA);
        $equipmentB = $this->equipmentOnVehicleOf($groupB);
        $issueA = EquipmentIssue::create(['equipment_id' => $equipmentA->id, 'description' => 'Guasto A', 'status' => 'open']);
        $issueB = EquipmentIssue::create(['equipment_id' => $equipmentB->id, 'description' => 'Guasto B', 'status' => 'open']);

        $visibleIds = EquipmentIssue::visibleToGroup($groupA->id)->pluck('id');

        $this->assertTrue($visibleIds->contains($issueA->id));
        $this->assertFalse($visibleIds->contains($issueB->id));
    }

    public function test_equipment_maintenance_record_visible_to_group_if_any_linked_equipment_is(): void
    {
        $groupA = $this->group('Gruppo A', 'EEEE5555');
        $groupB = $this->group('Gruppo B', 'FFFF6666');
        $equipmentA = $this->equipmentOnVehicleOf($groupA);
        $equipmentB = $this->equipmentOnVehicleOf($groupB);
        $provider = Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);
        $sharedRecord = EquipmentMaintenanceRecord::create(['provider_id' => $provider->id, 'appointment_date' => today()]);
        $sharedRecord->equipments()->attach([$equipmentA->id, $equipmentB->id]);
        $onlyBRecord = EquipmentMaintenanceRecord::create(['provider_id' => $provider->id, 'appointment_date' => today()]);
        $onlyBRecord->equipments()->attach($equipmentB->id);

        $visibleIds = EquipmentMaintenanceRecord::visibleToGroup($groupA->id)->pluck('id');

        $this->assertTrue($visibleIds->contains($sharedRecord->id));
        $this->assertFalse($visibleIds->contains($onlyBRecord->id));
    }

    public function test_visible_to_current_user_matches_the_authenticated_users_group(): void
    {
        $group = $this->group('Gruppo Mio', 'GGGG7777');
        $otherGroup = $this->group('Altro Gruppo', 'HHHH8888');
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);

        $own = $this->equipmentOnVehicleOf($group);
        $other = $this->equipmentOnVehicleOf($otherGroup);
        $unassigned = $this->equipmentOnVehicleOf(null);

        $this->actingAs($user);
        $visibleIds = Equipment::visibleToCurrentUser()->pluck('id');

        $this->assertTrue($visibleIds->contains($own->id));
        $this->assertTrue($visibleIds->contains($unassigned->id));
        $this->assertFalse($visibleIds->contains($other->id));
    }
}
