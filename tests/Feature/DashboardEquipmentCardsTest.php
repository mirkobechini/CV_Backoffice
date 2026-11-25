<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\EquipmentIssue;
use App\Models\EquipmentMaintenanceRecord;
use App\Models\EquipmentType;
use App\Models\Group;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Card dashboard "Guasti attrezzature aperti" e "Prossimi appuntamenti
 * attrezzature" (issue-685), file dedicato per non far crescere
 * DashboardCardsTest.php oltre la soglia dimensionale.
 */
class DashboardEquipmentCardsTest extends TestCase
{
    use RefreshDatabase;

    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    private function equipmentType(): EquipmentType
    {
        return EquipmentType::create(['name' => 'Estintore '.uniqid()]);
    }

    public function test_dashboard_shows_open_equipment_issue(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $equipment = Equipment::create([
            'equipment_type_id' => $this->equipmentType()->id,
            'name' => 'Estintore magazzino',
        ]);
        EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Manometro scarico',
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Manometro scarico');
        $response->assertSee('Estintore magazzino');
    }

    public function test_dashboard_hides_open_equipment_issue_from_another_group(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $otherGroup = Group::create(['name' => 'Altro gruppo', 'invite_code' => 'ZZZZ9999']);
        $vehicle = Vehicle::create([
            'license_plate' => 'ZZ999ZZ',
            'internal_code' => '9999',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $otherGroup->id,
        ]);
        $equipment = Equipment::create([
            'equipment_type_id' => $this->equipmentType()->id,
            'vehicle_id' => $vehicle->id,
            'name' => 'Estintore altrui',
        ]);
        EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Guasto non mio',
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Guasto non mio');
    }

    public function test_dashboard_shows_upcoming_equipment_appointment(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $equipment = Equipment::create([
            'equipment_type_id' => $this->equipmentType()->id,
            'name' => 'Estintore magazzino',
        ]);
        $provider = Provider::create(['name' => 'Officina Collaudi', 'type' => 'Centro Revisioni']);
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today()->addDays(3),
            'activity_type' => EquipmentMaintenanceRecord::ACTIVITY_COLLAUDO,
        ]);
        $record->equipments()->attach($equipment->id);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Estintore magazzino');
        $response->assertSee('Officina Collaudi');
    }

    public function test_dashboard_does_not_show_completed_equipment_appointment(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $equipment = Equipment::create([
            'equipment_type_id' => $this->equipmentType()->id,
            'name' => 'Estintore magazzino',
        ]);
        $provider = Provider::create(['name' => 'Officina Collaudi', 'type' => 'Centro Revisioni']);
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => today()->addDays(3),
            'return_date' => today(),
            'activity_type' => EquipmentMaintenanceRecord::ACTIVITY_COLLAUDO,
        ]);
        $record->equipments()->attach($equipment->id);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Nessun appuntamento imminente');
    }

    public function test_dashboard_shows_empty_states_when_nothing_to_report(): void
    {
        $user = User::factory()->withRole('admin')->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Tutte le attrezzature sono funzionanti');
        $response->assertSee('Tutte le attrezzature sono in regola');
    }

    public function test_dashboard_quick_add_menu_and_mobile_fab_offer_equipment_shortcuts(): void
    {
        $user = User::factory()->withRole('admin')->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('admin.equipment-issues.create'), false);
        $response->assertSee(route('admin.equipment-maintenance-records.create'), false);
        $response->assertSee('Nuovo guasto attrezzatura');
        $response->assertSee('Nuovo appuntamento attrezzatura');
    }
}
