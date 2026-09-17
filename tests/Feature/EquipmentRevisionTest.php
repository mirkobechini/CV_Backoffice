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
