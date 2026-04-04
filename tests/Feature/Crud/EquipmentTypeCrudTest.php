<?php

namespace Tests\Feature\Crud;


use App\Models\User;
use App\Models\EquipmentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentTypeCrudTest extends TestCase
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

    public function test_equipment_type_index_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.equipment-types.index'));

        $response->assertStatus(200);
    }


    public function test_equipment_type_create_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user

        $response = $this->actingAs($user)->get(route('admin.equipment-types.create'));

        $response->assertStatus(200);
    }



    public function test_equipment_type_show_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $equipmentType = $this->createEquipmentType();

        $response = $this->actingAs($user)->get(route('admin.equipment-types.show', $equipmentType));

        $response->assertStatus(200);
    }

    public function test_equipment_type_edit_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $equipmentType = $this->createEquipmentType();

        $response = $this->actingAs($user)->get(route('admin.equipment-types.edit', $equipmentType));

        $response->assertStatus(200);
    }


    public function test_equipment_type_can_be_stored(): void
    {
        $user = $this->createUser();    //fake user

        $response = $this->actingAs($user)->post(route('admin.equipment-types.store'), [
            'name' => 'Estintore',
            'first_inspection_months' => 6,
            'regular_inspection_months' => 6,
        ]);

        $equipmentType = EquipmentType::first();

        $response->assertRedirect(route('admin.equipment-types.show', $equipmentType));

        $this->assertDatabaseHas('equipment_types', [
            'name' => 'Estintore',
            'first_inspection_months' => 6,
            'regular_inspection_months' => 6,
        ]);
    }


    public function test_equipment_type_can_be_updated(): void
    {
        $user = $this->createUser();
        $equipmentType = $this->createEquipmentType();

        $response = $this->actingAs($user)->put(route('admin.equipment-types.update', $equipmentType), [
            'name' => 'Barella',
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);

        $response->assertRedirect(route('admin.equipment-types.show', $equipmentType));

        $this->assertDatabaseHas('equipment_types', [
            'name' => 'Barella',
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);
    }

    public function test_equipment_type_can_be_deleted(): void
    {
        $user = $this->createUser();

        $equipmentType = $this->createEquipmentType();

        $response = $this->actingAs($user)->delete(route('admin.equipment-types.destroy', $equipmentType));

        $response->assertRedirect(route('admin.equipment-types.index'));
        $this->assertDatabaseMissing('equipment_types', [
            'id' => $equipmentType->id,
        ]);
    }
}
