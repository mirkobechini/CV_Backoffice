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
        return User::factory()->withRole('admin')->create();
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


    public function test_index_shows_expiration_date_not_last_revision_date_alongside_countdown(): void
    {
        // Prima di questo fix, la cella mostrava revision_date (l'ultima
        // revisione fatta) come data principale ma calcolava il conto alla
        // rovescia sotto su expiration_date (la prossima scadenza) — due
        // date diverse mostrate insieme come se corrispondessero.
        $user = $this->createUser();
        ['equipment' => $equipment] = $this->createEquipment();

        $response = $this->actingAs($user)->get(route('admin.equipments.index'));

        $response->assertStatus(200);
        $response->assertSee($equipment->expiration_date_formatted);
        $response->assertDontSee($equipment->revision_date_formatted);
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


    public function test_index_groups_by_equipment_type(): void
    {
        $user = $this->createUser();
        $extinguisherType = EquipmentType::create(['name' => 'Estintore']);
        $stretcherType = EquipmentType::create(['name' => 'Barella']);
        Equipment::create(['equipment_type_id' => $extinguisherType->id, 'name' => 'Estintore A', 'serial_number' => 'SN-1']);
        Equipment::create(['equipment_type_id' => $extinguisherType->id, 'name' => 'Estintore B', 'serial_number' => 'SN-2']);
        Equipment::create(['equipment_type_id' => $stretcherType->id, 'name' => 'Barella A', 'serial_number' => 'SN-3']);

        $response = $this->actingAs($user)->get(route('admin.equipments.index', ['group_by' => 'type']));

        $response->assertStatus(200);
        $response->assertSee('Estintore (2)');
        $response->assertSee('Barella (1)');
    }

    public function test_index_sorts_by_name(): void
    {
        $user = $this->createUser();
        $type = $this->createEquipmentType();
        Equipment::create(['equipment_type_id' => $type->id, 'name' => 'Zeta', 'serial_number' => 'SN-Z']);
        Equipment::create(['equipment_type_id' => $type->id, 'name' => 'Alfa', 'serial_number' => 'SN-A']);

        $response = $this->actingAs($user)->get(route('admin.equipments.index', ['sort_by' => 'name', 'sort_dir' => 'asc']));

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'Zeta'), strpos($content, 'Alfa'));
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
            'expiration_date' => '2024-01',
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
            'expiration_date' => '2024-01',
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

    // VALIDAZIONE DEI CAMPI OBBLIGATORI

    public function test_equipment_name_is_required(): void
    {
        $user = $this->createUser();
        $equipmentType = $this->createEquipmentType();

        $response = $this->actingAs($user)->post(route('admin.equipments.store'), [
            'equipment_type_id' => $equipmentType->id,
            'serial_number' => '111111',
            'revision_date' => '2023-01-01',
            'expiration_date' => '2024-01',
        ]);

        // Verifica che il campo `name` sia obbligatorio.
        $response->assertSessionHasErrors(['name']);
        $this->assertDatabaseCount('equipment', 0); // Conferma che non venga creato alcun equipaggiamento senza nome.
    }
}
