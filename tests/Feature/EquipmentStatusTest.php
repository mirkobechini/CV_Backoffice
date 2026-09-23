<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Riproduce il bug: da Carbon 3 diffInDays() restituisce un valore con
     * segno (non più sempre assoluto), e chiamarlo nell'ordine sbagliato
     * ($dueDate->diffInDays($today) invece di $today->diffInDays($dueDate))
     * dava un numero negativo per qualunque scadenza futura — sempre <= 30,
     * quindi "In scadenza" anche a anni di distanza.
     */
    public function test_status_label_is_valid_when_expiration_is_years_away(): void
    {
        $equipmentType = EquipmentType::create(['name' => 'Estintore']);
        $equipment = Equipment::create([
            'equipment_type_id' => $equipmentType->id,
            'name' => 'Estintore 5kg',
            'expiration_date' => now()->addYears(6),
        ]);

        $this->assertSame('Valida', $equipment->status_label);
    }

    public function test_collaudo_status_label_is_valid_when_far_away(): void
    {
        $equipmentType = EquipmentType::create([
            'name' => 'Estintore',
            'category' => EquipmentType::CATEGORY_FIRE_EXTINGUISHER,
        ]);
        $equipment = Equipment::create([
            'equipment_type_id' => $equipmentType->id,
            'name' => 'Estintore 5kg',
            'next_collaudo_date' => now()->addYears(6),
        ]);

        $this->assertSame('Valido', $equipment->collaudo_status_label);
    }

    public function test_status_label_is_pending_within_warning_window(): void
    {
        $equipmentType = EquipmentType::create(['name' => 'Estintore']);
        $equipment = Equipment::create([
            'equipment_type_id' => $equipmentType->id,
            'name' => 'Estintore 5kg',
            'expiration_date' => now()->addDays(10),
        ]);

        $this->assertSame('In scadenza', $equipment->status_label);
    }

    public function test_expiration_date_accepts_month_year_input(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $equipmentType = EquipmentType::create(['name' => 'Estintore']);

        $response = $this->actingAs($user)->post(route('admin.equipments.store'), [
            'equipment_type_id' => $equipmentType->id,
            'name' => 'Estintore 5kg',
            'serial_number' => 'SN-1',
            'expiration_date' => '2030-06',
        ]);

        $response->assertRedirect();

        $equipment = Equipment::where('serial_number', 'SN-1')->first();
        $this->assertSame('2030-06-30', $equipment->expiration_date->toDateString());
    }

    public function test_next_collaudo_date_accepts_month_year_input(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $equipmentType = EquipmentType::create([
            'name' => 'Estintore',
            'category' => EquipmentType::CATEGORY_FIRE_EXTINGUISHER,
        ]);

        $response = $this->actingAs($user)->post(route('admin.equipments.store'), [
            'equipment_type_id' => $equipmentType->id,
            'name' => 'Estintore 5kg',
            'serial_number' => 'SN-2',
            'next_collaudo_date' => '2032-02',
        ]);

        $response->assertRedirect();

        $equipment = Equipment::where('serial_number', 'SN-2')->first();
        $this->assertSame('2032-02-29', $equipment->next_collaudo_date->toDateString());
    }

    public function test_expiration_date_rejects_full_day_format(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $equipmentType = EquipmentType::create(['name' => 'Estintore']);

        $response = $this->actingAs($user)->post(route('admin.equipments.store'), [
            'equipment_type_id' => $equipmentType->id,
            'name' => 'Estintore 5kg',
            'serial_number' => 'SN-3',
            'expiration_date' => '2030-06-15',
        ]);

        $response->assertSessionHasErrors(['expiration_date']);
    }
}
