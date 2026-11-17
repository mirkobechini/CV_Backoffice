<?php

namespace Tests\Feature\Superadmin;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\FleetDevice;
use App\Models\FuelCard;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::factory()->create(['is_superadmin' => true]);
    }

    public function test_requires_vehicle_confirmation(): void
    {
        $superadmin = $this->superadmin();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);

        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group->id,
        ]);

        $response = $this->actingAs($superadmin)->delete(route('superadmin.groups.destroy', $group), [
            'confirm_name' => 'Gruppo A',
        ]);

        $response->assertSessionHasErrors('delete_vehicles');
        $this->assertDatabaseHas('groups', ['id' => $group->id]);
    }

    public function test_requires_matching_name_confirmation(): void
    {
        $superadmin = $this->superadmin();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);

        $response = $this->actingAs($superadmin)->delete(route('superadmin.groups.destroy', $group), [
            'confirm_name' => 'Nome sbagliato',
        ]);

        $response->assertSessionHasErrors('confirm_name');
        $this->assertDatabaseHas('groups', ['id' => $group->id, 'deleted_at' => null]);
    }

    public function test_with_vehicle_confirmation_deletes_vehicles(): void
    {
        $superadmin = $this->superadmin();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);

        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group->id,
        ]);

        $response = $this->actingAs($superadmin)->delete(route('superadmin.groups.destroy', $group), [
            'delete_vehicles' => '1',
            'confirm_name' => 'Gruppo A',
        ]);

        $response->assertRedirect(route('superadmin.dashboard'));
        $this->assertSoftDeleted('groups', ['id' => $group->id]);
        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }

    public function test_with_vehicle_confirmation_deletes_orphaned_fleet_device_fuel_card_and_equipment(): void
    {
        // Audit sicurezza SM5: FleetDevice/FuelCard/Equipment non hanno un
        // group_id/vehicle_id "vivo" dopo questa cancellazione — senza
        // pulizia restavano orfani ma pienamente vivi e interrogabili.
        $superadmin = $this->superadmin();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);

        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group->id,
        ]);

        $equipmentType = EquipmentType::create(['name' => 'Estintore']);
        $equipment = Equipment::create([
            'vehicle_id' => $vehicle->id,
            'equipment_type_id' => $equipmentType->id,
            'name' => 'Estintore cabina',
        ]);
        $deviceOnVehicle = FleetDevice::create([
            'type' => FleetDevice::TYPE_TELEPASS,
            'code' => 'TP-001',
            'vehicle_id' => $vehicle->id,
            'group_id' => $group->id,
        ]);
        $unassignedDevice = FleetDevice::create([
            'type' => FleetDevice::TYPE_TELEPASS,
            'code' => 'TP-002',
            'group_id' => $group->id,
        ]);
        $cardOnVehicle = FuelCard::create([
            'card_number' => 'FC-001',
            'status' => FuelCard::STATUS_ASSIGNED,
            'vehicle_id' => $vehicle->id,
            'group_id' => $group->id,
        ]);
        $jollyCard = FuelCard::create([
            'card_number' => 'FC-JOLLY',
            'status' => FuelCard::STATUS_JOLLY,
            'group_id' => $group->id,
        ]);

        $response = $this->actingAs($superadmin)->delete(route('superadmin.groups.destroy', $group), [
            'delete_vehicles' => '1',
            'confirm_name' => 'Gruppo A',
        ]);

        $response->assertRedirect(route('superadmin.dashboard'));
        $this->assertDatabaseMissing('equipment', ['id' => $equipment->id]);
        $this->assertDatabaseMissing('fleet_devices', ['id' => $deviceOnVehicle->id]);
        $this->assertDatabaseMissing('fleet_devices', ['id' => $unassignedDevice->id]);
        $this->assertDatabaseMissing('fuel_cards', ['id' => $cardOnVehicle->id]);
        $this->assertDatabaseMissing('fuel_cards', ['id' => $jollyCard->id]);
    }

    public function test_without_vehicles_deletes_unassigned_fleet_device_and_fuel_card(): void
    {
        $superadmin = $this->superadmin();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $device = FleetDevice::create([
            'type' => FleetDevice::TYPE_TELEPASS,
            'code' => 'TP-001',
            'group_id' => $group->id,
        ]);
        $card = FuelCard::create([
            'card_number' => 'FC-JOLLY',
            'status' => FuelCard::STATUS_JOLLY,
            'group_id' => $group->id,
        ]);

        $response = $this->actingAs($superadmin)->delete(route('superadmin.groups.destroy', $group), [
            'confirm_name' => 'Gruppo A',
        ]);

        $response->assertRedirect(route('superadmin.dashboard'));
        $this->assertDatabaseMissing('fleet_devices', ['id' => $device->id]);
        $this->assertDatabaseMissing('fuel_cards', ['id' => $card->id]);
    }

    public function test_without_vehicles_succeeds_and_is_recoverable(): void
    {
        $superadmin = $this->superadmin();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $capo = User::factory()->create();
        $group->addUser($capo, Group::ROLE_CAPO);

        $response = $this->actingAs($superadmin)->delete(route('superadmin.groups.destroy', $group), [
            'confirm_name' => 'Gruppo A',
        ]);

        $response->assertRedirect(route('superadmin.dashboard'));
        $this->assertSoftDeleted('groups', ['id' => $group->id]);
        $this->assertNotNull(Group::withTrashed()->find($group->id));
        $this->assertDatabaseHas('group_user', ['group_id' => $group->id, 'user_id' => $capo->id]);
    }

    public function test_regular_user_cannot_delete_group(): void
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($user, Group::ROLE_CAPO);

        $response = $this->actingAs($user)->delete(route('superadmin.groups.destroy', $group), [
            'confirm_name' => 'Gruppo A',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('groups', ['id' => $group->id, 'deleted_at' => null]);
    }
}
