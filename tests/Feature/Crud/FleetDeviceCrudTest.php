<?php

namespace Tests\Feature\Crud;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\FleetDevice;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetDeviceCrudTest extends TestCase
{
    use RefreshDatabase;

    private function group(string $name = 'Gruppo A'): Group
    {
        return Group::create(['name' => $name, 'invite_code' => Group::generateInviteCode()]);
    }

    private function capo(Group $group): User
    {
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);

        return $user;
    }

    private function member(Group $group): User
    {
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_MEMBER);

        return $user;
    }

    private function vehicle(Group $group, string $internalCode = '0001'): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat '.$internalCode]);
        $model = CarModel::create(['name' => 'Ducato '.$internalCode, 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza '.$internalCode, 'needs_oxygen_check' => true]);

        return Vehicle::create([
            'license_plate' => 'AB'.rand(100, 999).'CD',
            'internal_code' => $internalCode,
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group->id,
        ]);
    }

    public function test_index_page_is_reachable(): void
    {
        $group = $this->group();
        $user = $this->capo($group);

        $response = $this->actingAs($user)->get(route('admin.fleet-devices.index'));

        $response->assertOk();
    }

    public function test_create_page_is_reachable(): void
    {
        $group = $this->group();
        $user = $this->capo($group);

        $response = $this->actingAs($user)->get(route('admin.fleet-devices.create'));

        $response->assertOk();
    }

    public function test_can_be_stored_without_vehicle(): void
    {
        $group = $this->group();
        $user = $this->capo($group);

        $response = $this->actingAs($user)->post(route('admin.fleet-devices.store'), [
            'type' => FleetDevice::TYPE_TELEPASS,
            'code' => 'TP-12345',
        ]);

        $device = FleetDevice::first();
        $response->assertRedirect(route('admin.fleet-devices.show', $device));
        $this->assertDatabaseHas('fleet_devices', [
            'id' => $device->id,
            'type' => FleetDevice::TYPE_TELEPASS,
            'code' => 'TP-12345',
            'vehicle_id' => null,
            'group_id' => $group->id,
        ]);
        $this->assertDatabaseCount('fleet_device_assignments', 0);
    }

    public function test_storing_with_a_vehicle_also_logs_the_first_assignment(): void
    {
        $group = $this->group();
        $user = $this->capo($group);
        $vehicle = $this->vehicle($group);

        $response = $this->actingAs($user)->post(route('admin.fleet-devices.store'), [
            'type' => FleetDevice::TYPE_UNIPOL_MOVE,
            'code' => 'UM-999',
            'vehicle_id' => $vehicle->id,
        ]);

        $device = FleetDevice::first();
        $response->assertRedirect(route('admin.fleet-devices.show', $device));
        $this->assertDatabaseHas('fleet_devices', ['id' => $device->id, 'vehicle_id' => $vehicle->id]);
        $this->assertDatabaseHas('fleet_device_assignments', [
            'fleet_device_id' => $device->id,
            'vehicle_id' => $vehicle->id,
            'previous_vehicle_id' => null,
            'plate_snapshot' => $vehicle->license_plate,
        ]);
    }

    public function test_reassign_logs_history_and_updates_current_vehicle(): void
    {
        $group = $this->group();
        $user = $this->capo($group);
        $originVehicle = $this->vehicle($group, '0001');
        $targetVehicle = $this->vehicle($group, '0002');
        $device = FleetDevice::create([
            'type' => FleetDevice::TYPE_TELEPASS,
            'code' => 'TP-1',
            'vehicle_id' => $originVehicle->id,
            'group_id' => $group->id,
        ]);

        $response = $this->actingAs($user)->post(route('admin.fleet-devices.reassign', $device), [
            'vehicle_id' => $targetVehicle->id,
            'assigned_date' => '2026-10-20',
        ]);

        $response->assertRedirect(route('admin.fleet-devices.show', $device));
        $this->assertDatabaseHas('fleet_devices', ['id' => $device->id, 'vehicle_id' => $targetVehicle->id]);
        $this->assertDatabaseHas('fleet_device_assignments', [
            'fleet_device_id' => $device->id,
            'vehicle_id' => $targetVehicle->id,
            'previous_vehicle_id' => $originVehicle->id,
            'plate_snapshot' => $targetVehicle->license_plate,
        ]);
    }

    public function test_member_cannot_reassign_device(): void
    {
        $group = $this->group();
        $member = $this->member($group);
        $vehicle = $this->vehicle($group);
        $device = FleetDevice::create([
            'type' => FleetDevice::TYPE_TELEPASS,
            'code' => 'TP-1',
            'group_id' => $group->id,
        ]);

        $response = $this->actingAs($member)->post(route('admin.fleet-devices.reassign', $device), [
            'vehicle_id' => $vehicle->id,
            'assigned_date' => '2026-10-20',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('fleet_devices', ['id' => $device->id, 'vehicle_id' => null]);
    }

    public function test_unassigned_device_is_not_visible_to_another_group(): void
    {
        // Diverge deliberatamente dal comportamento di Equipment non
        // assegnato (visibile a tutti i gruppi): un dispositivo non
        // assegnato resta comunque una risorsa della SUA associazione,
        // quindi invisibile alle altre.
        $groupA = $this->group('Gruppo A');
        $groupB = $this->group('Gruppo B');
        $userB = $this->capo($groupB);
        $device = FleetDevice::create([
            'type' => FleetDevice::TYPE_TELEPASS,
            'code' => 'TP-1',
            'group_id' => $groupA->id,
        ]);

        $response = $this->actingAs($userB)->get(route('admin.fleet-devices.show', $device));

        $response->assertForbidden();
    }

    public function test_can_be_deleted(): void
    {
        $group = $this->group();
        $user = $this->capo($group);
        $device = FleetDevice::create([
            'type' => FleetDevice::TYPE_TELEPASS,
            'code' => 'TP-1',
            'group_id' => $group->id,
        ]);

        $response = $this->actingAs($user)->delete(route('admin.fleet-devices.destroy', $device));

        $response->assertRedirect(route('admin.fleet-devices.index'));
        $this->assertDatabaseMissing('fleet_devices', ['id' => $device->id]);
    }
}
