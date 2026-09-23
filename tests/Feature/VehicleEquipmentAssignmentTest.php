<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleEquipmentAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function group(string $name): Group
    {
        return Group::create(['name' => $name, 'invite_code' => Group::generateInviteCode()]);
    }

    private function vehicle(Group $group, string $internalCode): Vehicle
    {
        return Vehicle::create([
            'license_plate' => 'AA' . rand(100, 999) . 'BB',
            'internal_code' => $internalCode,
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $group->id,
        ]);
    }

    public function test_capo_can_assign_unassigned_equipment_to_vehicle(): void
    {
        $group = $this->group('Gruppo A');
        $capo = User::factory()->create();
        $group->addUser($capo, Group::ROLE_CAPO);
        $vehicle = $this->vehicle($group, '0001');
        $type = EquipmentType::create(['name' => 'Estintore']);
        $equipment = Equipment::create(['equipment_type_id' => $type->id, 'name' => 'Estintore 5kg']);

        $response = $this->actingAs($capo)->post(route('admin.vehicles.equipment.assign', $vehicle), [
            'equipment_id' => $equipment->id,
        ]);

        $response->assertRedirect(route('admin.vehicles.show', $vehicle));
        $this->assertSame($vehicle->id, $equipment->fresh()->vehicle_id);
    }

    public function test_capo_can_move_equipment_from_another_vehicle_in_same_group(): void
    {
        $group = $this->group('Gruppo A');
        $capo = User::factory()->create();
        $group->addUser($capo, Group::ROLE_CAPO);
        $originVehicle = $this->vehicle($group, '0001');
        $targetVehicle = $this->vehicle($group, '0002');
        $type = EquipmentType::create(['name' => 'Estintore']);
        $equipment = Equipment::create([
            'equipment_type_id' => $type->id,
            'name' => 'Estintore 5kg',
            'vehicle_id' => $originVehicle->id,
        ]);

        $response = $this->actingAs($capo)->post(route('admin.vehicles.equipment.assign', $targetVehicle), [
            'equipment_id' => $equipment->id,
        ]);

        $response->assertRedirect(route('admin.vehicles.show', $targetVehicle));
        $this->assertSame($targetVehicle->id, $equipment->fresh()->vehicle_id);
    }

    public function test_member_cannot_assign_equipment(): void
    {
        $group = $this->group('Gruppo A');
        $member = User::factory()->create();
        $group->addUser($member, Group::ROLE_MEMBER);
        $vehicle = $this->vehicle($group, '0001');
        $type = EquipmentType::create(['name' => 'Estintore']);
        $equipment = Equipment::create(['equipment_type_id' => $type->id, 'name' => 'Estintore 5kg']);

        $response = $this->actingAs($member)->post(route('admin.vehicles.equipment.assign', $vehicle), [
            'equipment_id' => $equipment->id,
        ]);

        $response->assertForbidden();
        $this->assertNull($equipment->fresh()->vehicle_id);
    }

    public function test_cannot_move_equipment_belonging_to_another_group(): void
    {
        $groupA = $this->group('Gruppo A');
        $groupB = $this->group('Gruppo B');
        $capoA = User::factory()->create();
        $groupA->addUser($capoA, Group::ROLE_CAPO);
        $vehicleA = $this->vehicle($groupA, '0001');
        $vehicleB = $this->vehicle($groupB, '0002');
        $type = EquipmentType::create(['name' => 'Estintore']);
        $equipment = Equipment::create([
            'equipment_type_id' => $type->id,
            'name' => 'Estintore 5kg',
            'vehicle_id' => $vehicleB->id,
        ]);

        $response = $this->actingAs($capoA)->post(route('admin.vehicles.equipment.assign', $vehicleA), [
            'equipment_id' => $equipment->id,
        ]);

        $response->assertForbidden();
        $this->assertSame($vehicleB->id, $equipment->fresh()->vehicle_id);
    }

    public function test_show_page_lists_unassigned_and_other_vehicle_equipment_as_assignable(): void
    {
        $group = $this->group('Gruppo A');
        $capo = User::factory()->create();
        $group->addUser($capo, Group::ROLE_CAPO);
        $vehicle = $this->vehicle($group, '0001');
        $otherVehicle = $this->vehicle($group, '0002');
        $type = EquipmentType::create(['name' => 'Estintore']);
        $unassigned = Equipment::create(['equipment_type_id' => $type->id, 'name' => 'Estintore libero', 'serial_number' => 'SN-FREE']);
        $onOtherVehicle = Equipment::create(['equipment_type_id' => $type->id, 'name' => 'Estintore altrove', 'serial_number' => 'SN-OTHER', 'vehicle_id' => $otherVehicle->id]);
        $alreadyHere = Equipment::create(['equipment_type_id' => $type->id, 'name' => 'Estintore qui', 'serial_number' => 'SN-HERE', 'vehicle_id' => $vehicle->id]);

        $response = $this->actingAs($capo)->get(route('admin.vehicles.show', $vehicle));

        $response->assertOk();
        $response->assertSee('SN-FREE');
        $response->assertSee('SN-OTHER');
    }
}
