<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function vehicle(): Vehicle
    {
        $vt = VehicleType::create(['name' => 'Ambulanza', 'needs_oxygen_check' => true, 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        return Vehicle::create(['license_plate' => 'AB123CD', 'vehicle_type_id' => $vt->id, 'internal_code' => '1234', 'brand_id' => null, 'car_model_id' => null, 'fuel_type' => 'diesel', 'immatricolation_date' => '2024-01-01']);
    }

    public function test_index_returns_view(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.equipments.index'))
            ->assertOk();
    }

    public function test_create_returns_view(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.equipments.create'))
            ->assertOk();
    }

    public function test_store_creates_equipment(): void
    {
        $eqType = EquipmentType::create(['name' => 'Estintore']);
        $v = $this->vehicle();

        $this->actingAs($this->admin())
            ->post(route('admin.equipments.store'), [
                'vehicle_id' => $v->id,
                'equipment_type_id' => $eqType->id,
                'name' => 'Estintore ABC',
                'serial_number' => 'SN123',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('equipment', ['serial_number' => 'SN123']);
    }

    public function test_show_displays_equipment(): void
    {
        $eqType = EquipmentType::create(['name' => 'Estintore']);
        $v = $this->vehicle();
        $eq = Equipment::create(['vehicle_id' => $v->id, 'equipment_type_id' => $eqType->id, 'name' => 'Test', 'serial_number' => 'SN456']);

        // La show carica equipmentType, quindi funziona anche con name null su equipmentType
        $this->actingAs($this->admin())
            ->get(route('admin.equipments.show', $eq))
            ->assertOk();
    }

    public function test_update_modifies_equipment(): void
    {
        $eqType = EquipmentType::create(['name' => 'Estintore']);
        $v = $this->vehicle();
        $eq = Equipment::create(['vehicle_id' => $v->id, 'equipment_type_id' => $eqType->id, 'name' => 'Vecchio', 'serial_number' => 'SN789']);

        $this->actingAs($this->admin())
            ->put(route('admin.equipments.update', $eq), [
                'vehicle_id' => $eq->vehicle_id,
                'equipment_type_id' => $eq->equipment_type_id,
                'name' => 'Nuovo Nome',
                'serial_number' => $eq->serial_number,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('equipment', ['name' => 'Nuovo Nome']);
    }

    public function test_destroy_deletes_equipment(): void
    {
        $eqType = EquipmentType::create(['name' => 'Estintore']);
        $v = $this->vehicle();
        $eq = Equipment::create(['vehicle_id' => $v->id, 'equipment_type_id' => $eqType->id, 'name' => 'Test', 'serial_number' => 'SN000']);

        $this->actingAs($this->admin())
            ->delete(route('admin.equipments.destroy', $eq))
            ->assertRedirect();

        $this->assertDatabaseMissing('equipment', ['id' => $eq->id]);
    }
}
