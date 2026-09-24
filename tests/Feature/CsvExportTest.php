<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Group;
use App\Models\Issue;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    /**
     * Stesso gruppo creato dallo stato "admin" di UserFactory::withRole():
     * il veicolo deve appartenervi per essere accessibile (le query di
     * export sono filtrate per gruppo).
     */
    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    private function vehicle(): Vehicle
    {
        $vt = VehicleType::create(['name' => 'Ambulanza', 'needs_oxygen_check' => true, 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vt->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ]);
    }

    public function test_export_vehicles_returns_csv(): void
    {
        $this->vehicle();
        $response = $this->actingAs($this->admin())->get(route('admin.csv.export', 'vehicles'));
        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="veicoli.csv"');
    }

    public function test_export_issues_returns_csv(): void
    {
        $vehicle = $this->vehicle();
        Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Guasto', 'event_date' => '2025-01-01']);
        $response = $this->actingAs($this->admin())->get(route('admin.csv.export', 'issues'));
        $response->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="guasti.csv"');
    }

    public function test_export_deadlines_returns_csv(): void
    {
        $vehicle = $this->vehicle();
        Deadline::create(['vehicle_id' => $vehicle->id, 'type' => Deadline::TYPE_MINISTERIAL, 'due_date' => '2025-06-01']);
        $response = $this->actingAs($this->admin())->get(route('admin.csv.export', 'deadlines'));
        $response->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="scadenze.csv"');
    }

    public function test_export_invalid_entity_returns_404(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.csv.export', 'invalid'));
        $response->assertStatus(404);
    }

    public function test_export_requires_auth(): void
    {
        $response = $this->get(route('admin.csv.export', 'vehicles'));
        $response->assertRedirect(route('login'));
    }

    /**
     * Excel/Sheets/LibreOffice interpretano come formula qualunque cella
     * che inizia con =, +, -, @, tab o ritorno a capo: un campo testo
     * libero (es. descrizione guasto) scritto da un membro qualunque
     * poteva eseguire una formula quando il CSV veniva riaperto da
     * qualcun altro.
     */
    public function test_export_neutralizes_formula_injection_in_free_text_fields(): void
    {
        $vehicle = $this->vehicle();
        Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => '=HYPERLINK("http://evil.example","click")',
            'event_date' => '2025-01-01',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.csv.export', 'issues'));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringNotContainsString(';=HYPERLINK', $content);
    }
}
