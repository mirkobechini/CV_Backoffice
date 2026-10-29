<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Equipment;
use App\Models\EquipmentIssue;
use App\Models\EquipmentMaintenanceRecord;
use App\Models\EquipmentType;
use App\Models\Group;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    private function vehicle(string $internalCode = '0001', ?Group $group = null): Vehicle
    {
        $brand = Brand::firstOrCreate(['name' => 'Fiat']);
        $model = CarModel::firstOrCreate(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::firstOrCreate(
            ['name' => 'Ambulanza'],
            ['first_inspection_months' => 48, 'regular_inspection_months' => 24]
        );

        return Vehicle::create([
            'license_plate' => 'AB'.$internalCode.'CD',
            'internal_code' => $internalCode,
            'vehicle_type_id' => $type->id,
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => ($group ?? $this->defaultGroup())->id,
        ]);
    }

    private function equipment(?Vehicle $vehicle, string $name = 'Estintore test', ?string $typeName = null): Equipment
    {
        $type = EquipmentType::create(['name' => $typeName ?? ('Tipo '.uniqid())]);

        return Equipment::create([
            'equipment_type_id' => $type->id,
            'vehicle_id' => $vehicle?->id,
            'name' => $name,
            'serial_number' => 'SN-'.uniqid(),
        ]);
    }

    private function provider(string $name = 'Officina Test'): Provider
    {
        return Provider::create(['name' => $name, 'type' => 'Meccanico']);
    }

    public function test_issue_count_by_equipment_ranks_most_problematic_first(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $equipmentA = $this->equipment($vehicle, 'Estintore A');
        $equipmentB = $this->equipment($vehicle, 'Estintore B');

        EquipmentIssue::create(['equipment_id' => $equipmentA->id, 'description' => 'a', 'status' => 'open', 'event_date' => '2026-01-01']);
        EquipmentIssue::create(['equipment_id' => $equipmentA->id, 'description' => 'b', 'status' => 'open', 'event_date' => '2026-01-02']);
        EquipmentIssue::create(['equipment_id' => $equipmentB->id, 'description' => 'c', 'status' => 'open', 'event_date' => '2026-01-03']);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'attrezzature', 'period' => 'all']));

        $response->assertOk();
        $response->assertSeeInOrder(['Estintore A', 'Estintore B']);
    }

    public function test_issue_count_by_type_groups_by_equipment_type(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $equipment = $this->equipment($vehicle, 'Estintore test', 'Estintore');

        EquipmentIssue::create(['equipment_id' => $equipment->id, 'description' => 'a', 'status' => 'open', 'event_date' => '2026-01-01']);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'attrezzature', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('Guasti per tipo di attrezzatura');
        $response->assertSee('Estintore');
    }

    public function test_equipment_cost_by_year_and_by_equipment(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $equipment = $this->equipment($vehicle, 'Barella test');
        $provider = $this->provider();

        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-01',
            'activity_type' => EquipmentMaintenanceRecord::ACTIVITY_RIPARAZIONE,
            'cost' => 150,
        ]);
        $record->equipments()->attach($equipment->id);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'attrezzature', 'period' => 'all']));

        $response->assertOk();
        $response->assertSeeInOrder(['Spesa manutenzione per attrezzatura', 'Barella test', '150,00']);
    }

    public function test_equipment_cost_is_split_equally_across_multiple_equipments(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $equipmentA = $this->equipment($vehicle, 'Estintore A');
        $equipmentB = $this->equipment($vehicle, 'Estintore B');
        $provider = $this->provider();

        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-01',
            'activity_type' => EquipmentMaintenanceRecord::ACTIVITY_COLLAUDO,
            'cost' => 200,
        ]);
        $record->equipments()->attach([$equipmentA->id, $equipmentB->id]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'attrezzature', 'period' => 'all']));

        $response->assertOk();
        // 200€ diviso tra 2 attrezzature = 100€ ciascuna (il totale non
        // diviso, 200,00, compare comunque nella card "per anno" sopra).
        $response->assertSeeInOrder(['Spesa manutenzione per attrezzatura', '100,00', '100,00']);
    }

    public function test_unassigned_equipment_is_visible_to_all_groups(): void
    {
        $user = $this->admin();
        // Attrezzatura senza veicolo: condivisa tra gruppi (comportamento
        // deliberato, stesso di EquipmentIssueController).
        $unassignedEquipment = $this->equipment(null, 'Barella condivisa');

        EquipmentIssue::create(['equipment_id' => $unassignedEquipment->id, 'description' => 'a', 'status' => 'open', 'event_date' => '2026-01-01']);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'attrezzature', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('Barella condivisa');
    }

    public function test_equipment_statistics_exclude_another_groups_vehicle_assigned_equipment(): void
    {
        $user = $this->admin();
        $otherGroup = Group::create(['name' => 'Altro gruppo', 'invite_code' => Group::generateInviteCode()]);
        $otherVehicle = $this->vehicle('9999', $otherGroup);
        $otherEquipment = $this->equipment($otherVehicle, 'Estintore altro gruppo');

        EquipmentIssue::create(['equipment_id' => $otherEquipment->id, 'description' => 'a', 'status' => 'open', 'event_date' => '2026-01-01']);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'attrezzature', 'period' => 'all']));

        $response->assertOk();
        $response->assertDontSee('Estintore altro gruppo');
    }
}
