<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    private function extinguisherEquipment(): Equipment
    {
        $type = EquipmentType::create([
            'name' => 'Estintore Test',
            'category' => EquipmentType::CATEGORY_FIRE_EXTINGUISHER,
            'regular_inspection_months' => 6,
            'collaudo_interval_months' => 60,
        ]);

        return Equipment::create([
            'name' => 'Estintore',
            'equipment_type_id' => $type->id,
            'serial_number' => 'EX-100',
        ]);
    }

    public function test_recording_a_revision_updates_revision_and_expiration_dates(): void
    {
        $user = $this->admin();
        $equipment = $this->extinguisherEquipment();

        $this->actingAs($user)->post(route('admin.equipments.record-revision', $equipment), [
            'kind' => 'revision',
            'performed_date' => '2026-01-01',
        ]);

        $equipment->refresh();
        $this->assertSame('2026-01-01', $equipment->revision_date->toDateString());
        $this->assertSame('2026-07-01', $equipment->expiration_date->toDateString());

        $this->assertDatabaseHas('equipment_revisions', [
            'equipment_id' => $equipment->id,
            'kind' => 'revision',
            'performed_date' => '2026-01-01 00:00:00',
        ]);
    }

    public function test_recording_a_collaudo_updates_collaudo_and_next_collaudo_dates(): void
    {
        $user = $this->admin();
        $equipment = $this->extinguisherEquipment();

        $this->actingAs($user)->post(route('admin.equipments.record-revision', $equipment), [
            'kind' => 'collaudo',
            'performed_date' => '2026-01-01',
        ]);

        $equipment->refresh();
        $this->assertSame('2026-01-01', $equipment->collaudo_date->toDateString());
        $this->assertSame('2031-01-01', $equipment->next_collaudo_date->toDateString());

        $this->assertDatabaseHas('equipment_revisions', [
            'equipment_id' => $equipment->id,
            'kind' => 'collaudo',
        ]);

        // Non deve toccare la revisione ordinaria.
        $this->assertNull($equipment->revision_date);
    }

    public function test_recording_multiple_revisions_builds_history_and_count(): void
    {
        $user = $this->admin();
        $equipment = $this->extinguisherEquipment();

        $this->actingAs($user)->post(route('admin.equipments.record-revision', $equipment), [
            'kind' => 'revision',
            'performed_date' => '2025-01-01',
        ]);
        $this->actingAs($user)->post(route('admin.equipments.record-revision', $equipment), [
            'kind' => 'revision',
            'performed_date' => '2025-07-01',
        ]);

        $this->assertSame(2, $equipment->fresh()->revision_count);
        $this->assertDatabaseCount('equipment_revisions', 2);
    }

    public function test_bulk_record_revision_applies_same_date_to_all_selected(): void
    {
        $user = $this->admin();
        $extinguisherA = $this->extinguisherEquipment();
        $extinguisherB = Equipment::create([
            'name' => 'Estintore B',
            'equipment_type_id' => $extinguisherA->equipment_type_id,
            'serial_number' => 'EX-101',
        ]);

        $response = $this->actingAs($user)->post(route('admin.equipments.bulk-record-revision'), [
            'equipment_ids' => [$extinguisherA->id, $extinguisherB->id],
            'kind' => 'revision',
            'performed_date' => '2026-02-10',
        ]);

        $response->assertRedirect(route('admin.equipments.index'));

        foreach ([$extinguisherA, $extinguisherB] as $equipment) {
            $equipment->refresh();
            $this->assertSame('2026-02-10', $equipment->revision_date->toDateString());
            $this->assertSame('2026-08-10', $equipment->expiration_date->toDateString());
        }

        $this->assertDatabaseCount('equipment_revisions', 2);
    }

    public function test_bulk_record_revision_rejects_equipment_from_another_group(): void
    {
        $user = $this->admin();
        $equipment = $this->extinguisherEquipment();

        $otherGroup = \App\Models\Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);
        $otherVehicleType = \App\Models\VehicleType::create(['name' => 'Ambulanza B', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $otherBrand = \App\Models\Brand::create(['name' => 'Iveco']);
        $otherModel = \App\Models\CarModel::create(['name' => 'Daily', 'brand_id' => $otherBrand->id]);
        $otherVehicle = \App\Models\Vehicle::create([
            'group_id' => $otherGroup->id,
            'license_plate' => 'ZZ999ZZ',
            'internal_code' => '0002',
            'brand_id' => $otherBrand->id,
            'car_model_id' => $otherModel->id,
            'vehicle_type_id' => $otherVehicleType->id,
            'immatricolation_date' => '2024-01-01',
        ]);
        $otherEquipment = Equipment::create([
            'name' => 'Estintore altro gruppo',
            'equipment_type_id' => $equipment->equipmentType->id,
            'serial_number' => 'EX-200',
            'vehicle_id' => $otherVehicle->id,
        ]);

        $response = $this->actingAs($user)->post(route('admin.equipments.bulk-record-revision'), [
            'equipment_ids' => [$otherEquipment->id],
            'kind' => 'revision',
            'performed_date' => '2026-02-10',
        ]);

        $response->assertSessionHasErrors('equipment_ids');
        $this->assertDatabaseCount('equipment_revisions', 0);
    }

    public function test_kind_is_required(): void
    {
        $user = $this->admin();
        $equipment = $this->extinguisherEquipment();

        $response = $this->actingAs($user)->post(route('admin.equipments.record-revision', $equipment), [
            'performed_date' => '2026-01-01',
        ]);

        $response->assertSessionHasErrors(['kind']);
        $this->assertDatabaseCount('equipment_revisions', 0);
    }
}
