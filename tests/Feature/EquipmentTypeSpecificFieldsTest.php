<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentTypeSpecificFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    private function extinguisherType(array $overrides = []): EquipmentType
    {
        return EquipmentType::create(array_merge([
            'name' => 'Estintore Test',
            'category' => EquipmentType::CATEGORY_FIRE_EXTINGUISHER,
            'regular_inspection_months' => 6,
            'collaudo_interval_months' => 60,
        ], $overrides));
    }

    private function chairType(): EquipmentType
    {
        return EquipmentType::create([
            'name' => 'Sedia Test',
            'category' => EquipmentType::CATEGORY_CHAIR,
        ]);
    }

    public function test_store_saves_extinguisher_specific_fields(): void
    {
        $user = $this->admin();
        $type = $this->extinguisherType();

        $this->actingAs($user)->post(route('admin.equipments.store'), [
            'name' => 'Estintore cabina',
            'equipment_type_id' => $type->id,
            'brand' => 'Saval',
            'model' => 'P50',
            'serial_number' => 'EX-001',
            'identification_number' => 'ID-01',
            'extinguisher_agent' => 'co2',
            'weight_kg' => 5.5,
            'collaudo_date' => '2024-01-01',
        ]);

        $this->assertDatabaseHas('equipment', [
            'serial_number' => 'EX-001',
            'extinguisher_agent' => 'co2',
            'weight_kg' => 5.5,
            'brand' => 'Saval',
            'model' => 'P50',
            'identification_number' => 'ID-01',
        ]);

        // next_collaudo_date auto-calcolato da collaudo_date + collaudo_interval_months.
        $equipment = Equipment::where('serial_number', 'EX-001')->first();
        $this->assertSame('2029-01-01', $equipment->next_collaudo_date->toDateString());
    }

    public function test_store_saves_chair_specific_fields(): void
    {
        $user = $this->admin();
        $type = $this->chairType();

        $this->actingAs($user)->post(route('admin.equipments.store'), [
            'name' => 'Sedia portantina',
            'equipment_type_id' => $type->id,
            'serial_number' => 'CH-001',
            'chair_type' => 'manual_4_wheel',
            'max_weight_kg' => 150,
        ]);

        $this->assertDatabaseHas('equipment', [
            'serial_number' => 'CH-001',
            'chair_type' => 'manual_4_wheel',
            'max_weight_kg' => 150,
        ]);
    }

    public function test_needs_exchange_is_true_once_revision_count_reaches_threshold(): void
    {
        $type = $this->extinguisherType(['max_revisions_before_exchange' => 2]);
        $equipment = Equipment::create([
            'name' => 'Estintore',
            'equipment_type_id' => $type->id,
            'serial_number' => 'EX-002',
        ]);

        $this->assertFalse($equipment->needs_exchange);

        $equipment->revisions()->create(['kind' => 'revision', 'performed_date' => '2024-01-01']);
        $this->assertFalse($equipment->fresh()->needs_exchange);

        $equipment->revisions()->create(['kind' => 'revision', 'performed_date' => '2025-01-01']);
        $this->assertTrue($equipment->fresh()->needs_exchange);
    }

    public function test_collaudo_revisions_do_not_count_toward_exchange_threshold(): void
    {
        $type = $this->extinguisherType(['max_revisions_before_exchange' => 1]);
        $equipment = Equipment::create([
            'name' => 'Estintore',
            'equipment_type_id' => $type->id,
            'serial_number' => 'EX-003',
        ]);

        $equipment->revisions()->create(['kind' => 'collaudo', 'performed_date' => '2024-01-01']);

        $this->assertSame(0, $equipment->fresh()->revision_count);
        $this->assertFalse($equipment->fresh()->needs_exchange);
    }
}
