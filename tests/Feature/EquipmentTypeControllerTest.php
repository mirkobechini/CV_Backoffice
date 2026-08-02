<?php

namespace Tests\Feature;

use App\Models\EquipmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_index_returns_view(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.equipment-types.index'))
            ->assertOk();
    }

    public function test_create_returns_view(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.equipment-types.create'))
            ->assertOk();
    }

    public function test_store_creates_equipment_type(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.equipment-types.store'), [
                'name' => 'Estintore',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('equipment_types', ['name' => 'Estintore']);
    }

    public function test_show_displays_equipment_type(): void
    {
        $eqType = EquipmentType::create(['name' => 'Estintore']);

        $this->actingAs($this->admin())
            ->get(route('admin.equipment-types.show', $eqType))
            ->assertOk();
    }

    public function test_update_modifies_equipment_type(): void
    {
        $eqType = EquipmentType::create(['name' => 'Vecchio']);

        $this->actingAs($this->admin())
            ->put(route('admin.equipment-types.update', $eqType), [
                'name' => 'Nuovo Tipo',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('equipment_types', ['name' => 'Nuovo Tipo']);
    }

    public function test_destroy_deletes_equipment_type(): void
    {
        $eqType = EquipmentType::create(['name' => 'Da Eliminare']);

        $this->actingAs($this->admin())
            ->delete(route('admin.equipment-types.destroy', $eqType))
            ->assertRedirect();

        $this->assertDatabaseMissing('equipment_types', ['id' => $eqType->id]);
    }
}
