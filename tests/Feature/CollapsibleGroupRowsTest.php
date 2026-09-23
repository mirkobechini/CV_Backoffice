<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Group;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le tabelle raggruppate (guasti, scadenze, appuntamenti) espongono le
 * righe di intestazione gruppo e le righe di dettaglio con gli attributi
 * data-group-row/data-groups che il JS in resources/js/app.js usa per
 * pieghettarle al click. Qui si verifica solo il markup: il comportamento
 * di collapse è client-side, non testabile via richieste HTTP.
 */
class CollapsibleGroupRowsTest extends TestCase
{
    use RefreshDatabase;

    private function group(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    private function vehicle(): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $vehicleType = VehicleType::create(['name' => 'Ambulanza', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);

        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '1234',
            'vehicle_type_id' => $vehicleType->id,
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->group()->id,
        ]);
    }

    public function test_issues_index_grouped_by_vehicle_exposes_collapsible_markup(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $vehicle = $this->vehicle();
        Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Rumore al motore',
            'status' => 'open',
            'event_date' => '2025-01-02',
        ]);

        $response = $this->actingAs($user)->get(route('admin.issues.index', ['group_by' => 'vehicle']));

        $response->assertOk();
        $response->assertSee('data-group-row="g0"', false);
        $response->assertSee('data-groups="g0"', false);
        $response->assertSee('group-chevron', false);
    }

    public function test_maintenance_records_index_grouped_by_vehicle_exposes_collapsible_markup(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $vehicle = $this->vehicle();
        $provider = Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.index', ['group_by' => 'vehicle']));

        $response->assertOk();
        $response->assertSee('data-group-row="g0"', false);
        $response->assertSee('data-groups="g0"', false);
    }

    public function test_deadlines_index_grouped_by_vehicle_exposes_collapsible_markup(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => now()->addDays(30),
            'status' => Deadline::STATUS_VALID,
        ]);

        $response = $this->actingAs($user)->get(route('admin.deadlines.index', ['group_by' => 'vehicle']));

        $response->assertOk();
        $response->assertSee('data-group-row="g', false);
        $response->assertSee('data-groups="g', false);
    }
}
