<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_index_returns_view(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.vehicle-types.index'))
            ->assertOk();
    }

    public function test_create_returns_view(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.vehicle-types.create'))
            ->assertOk();
    }

    public function test_store_creates_vehicle_type(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.vehicle-types.store'), [
                'name' => 'Ambulanza',
                'needs_oxygen_check' => true,
                'extinguishers_required' => 2,
                'first_inspection_months' => 48,
                'regular_inspection_months' => 24,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_types', ['name' => 'Ambulanza']);
    }

    public function test_show_displays_vehicle_type(): void
    {
        $vt = VehicleType::create(['name' => 'Ambulanza', 'needs_oxygen_check' => true, 'extinguishers_required' => 2, 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);

        $this->actingAs($this->admin())
            ->get(route('admin.vehicle-types.show', $vt))
            ->assertOk();
    }

    public function test_update_modifies_vehicle_type(): void
    {
        $vt = VehicleType::create(['name' => 'Vecchio', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);

        $this->actingAs($this->admin())
            ->put(route('admin.vehicle-types.update', $vt), [
                'name' => 'Nuovo Tipo',
                'needs_oxygen_check' => false,
                'extinguishers_required' => 1,
                'first_inspection_months' => 60,
                'regular_inspection_months' => 30,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicle_types', ['name' => 'Nuovo Tipo']);
    }

    public function test_destroy_deletes_vehicle_type(): void
    {
        $vt = VehicleType::create(['name' => 'Da Eliminare', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);

        $this->actingAs($this->admin())
            ->delete(route('admin.vehicle-types.destroy', $vt))
            ->assertRedirect();

        $this->assertDatabaseMissing('vehicle_types', ['id' => $vt->id]);
    }

    public function test_store_with_equipment_requirements(): void
    {
        $eqType = \App\Models\EquipmentType::create(['name' => 'Estintore']);

        $this->actingAs($this->admin())
            ->post(route('admin.vehicle-types.store'), [
                'name' => 'Ambulanza',
                'needs_oxygen_check' => true,
                'extinguishers_required' => 2,
                'first_inspection_months' => 48,
                'regular_inspection_months' => 24,
                'required_equipment_types' => [$eqType->id],
                'required_equipment_types_qty' => [2],
            ])
            ->assertRedirect();

        $vt = VehicleType::where('name', 'Ambulanza')->first();
        $this->assertNotNull($vt);
        $this->assertDatabaseHas('vehicle_type_equipment_requirements', [
            'vehicle_type_id' => $vt->id,
            'equipment_type_id' => $eqType->id,
            'required_quantity' => 2,
        ]);
    }
}
