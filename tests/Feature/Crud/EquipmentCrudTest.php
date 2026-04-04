<?php

namespace Tests\Feature\Crud;

use App\Models\User;
use App\Models\Equipment;
use App\Models\EquipmentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentCrudTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->create();
    }

    private function createEquipmentType(): EquipmentType
    {

        return EquipmentType::create([
            'name' => 'Estintore',
            'first_inspection_months' => 6,
            'regular_inspection_months' => 6,
        ]);
    }

    private function createEquipment(): array
    {
        $equipmentType = $this->createEquipmentType();

        $equipment = Equipment::create([
            'equipment_type_id' => $equipmentType->id,
            'name' => 'Prova',
            'serial_number' => '111111',
            'revision_date' => '2023-01-01',
            'expiration_date' => '2024-01-01',
        ]);

        return compact('equipmentType', 'equipment');
    }

    public function test_equipment_index_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.equipments.index'));

        $response->assertStatus(200);
    }


    public function test_equipment_create_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user

        $response = $this->actingAs($user)->get(route('admin.equipments.create'));

        $response->assertStatus(200);
    }



    public function test_equipment_show_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $equipment = $this->createEquipment()['equipment'];

        $response = $this->actingAs($user)->get(route('admin.equipments.show', $equipment));

        $response->assertStatus(200);
    }

    public function test_equipment_edit_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $equipment = $this->createEquipment()['equipment'];

        $response = $this->actingAs($user)->get(route('admin.equipments.edit', $equipment));

        $response->assertStatus(200);
    }


    public function test_equipment_can_be_stored(): void
    {
        $user = $this->createUser();    //fake user
        $equipmentType = $this->createEquipmentType();

        $response = $this->actingAs($user)->post(route('admin.equipments.store'), [
            'equipment_type_id' => $equipmentType->id,
            'name' => 'Prova',
            'serial_number' => '111111',
            'revision_date' => '2023-01-01',
            'expiration_date' => '2024-01-01',
        ]);

        $equipment = Equipment::first();

        $response->assertRedirect(route('admin.equipments.show', $equipment));

        $this->assertDatabaseHas('equipment', [
            'equipment_type_id' => $equipmentType->id,
            'name' => 'Prova',
            'serial_number' => '111111',
        ]);
    }


    public function test_equipment_can_be_updated(): void
    {
        $user = $this->createUser();
        $data = $this->createEquipment();

        $equipment = $data['equipment'];
        $equipmentType = $data['equipmentType'];

        $response = $this->actingAs($user)->put(route('admin.equipments.update', $equipment), [
            'equipment_type_id' => $equipmentType->id,
            'name' => 'Prova3',
            'serial_number' => '333333',
            'revision_date' => '2023-01-01',
            'expiration_date' => '2024-01-01',
        ]);

        $response->assertRedirect(route('admin.equipments.show', $equipment));

        $this->assertDatabaseHas('equipment', [
            'equipment_type_id' => $equipmentType->id,
            'name' => 'Prova3',
            'serial_number' => '333333',
        ]);
    }

    public function test_equipment_can_be_deleted(): void
    {
        $user = $this->createUser();

        $equipment = $this->createEquipment()['equipment'];

        $response = $this->actingAs($user)->delete(route('admin.equipments.destroy', $equipment));

        $response->assertRedirect(route('admin.equipments.index'));
        $this->assertDatabaseMissing('equipment', [
            'id' => $equipment->id,
        ]);
    }
}
