<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    /**
     * Stesso gruppo creato dallo stato "admin" di UserFactory::withRole():
     * il veicolo deve appartenervi per essere trovato dalle query di
     * import, filtrate per gruppo.
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

    public function test_preview_requires_auth(): void
    {
        $response = $this->post(route('admin.csv-import.preview'), []);
        $response->assertRedirect(route('login'));
    }

    public function test_preview_requires_valid_entity(): void
    {
        $file = UploadedFile::fake()->createWithContent('test.csv', "DESCRIZIONE\nGuasto motore\n");
        $response = $this->actingAs($this->admin())
            ->from(route('admin.csv-import.index'))
            ->post(route('admin.csv-import.preview'), [
                'entity' => 'invalid',
                'csv_file' => $file,
            ]);
        $response->assertRedirect(route('admin.csv-import.index'));
    }

    public function test_preview_parses_issues_csv(): void
    {
        $this->vehicle();
        $file = UploadedFile::fake()->createWithContent('test.csv', "DESCRIZIONE\nGuasto motore\nFreno bloccato\n");
        $response = $this->actingAs($this->admin())
            ->post(route('admin.csv-import.preview'), [
                'entity' => 'issues',
                'csv_file' => $file,
                'vehicle_ref' => '1234',
            ]);
        $response->assertOk();
    }

    /**
     * preview() risolve _vehicle_id scoperto per gruppo, ma confirm() lo
     * riceve indietro solo come campo nascosto del form: senza
     * ricontrollarlo, un utente poteva alterarlo prima di confermare e
     * importare dati sul veicolo di un altro gruppo.
     */
    /**
     * validateMileageLogsPivot() batcha il controllo duplicati in un'unica
     * query (vedi CsvImportController) invece di una exists() per ogni
     * cella veicolo×mese: qui verifica che il duplicato venga comunque
     * rilevato correttamente dopo la modifica.
     */
    public function test_preview_pivot_flags_existing_mileage_log_as_duplicate(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        \App\Models\MileageLog::create([
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-01-01',
            'mileage' => 1000,
        ]);

        $csv = "SIGLA,TARGA,GENNAIO,FEBBRAIO\n1234,AB123CD,1200,1500\n";
        $file = UploadedFile::fake()->createWithContent('pivot.csv', $csv);

        $response = $this->actingAs($user)->post(route('admin.csv-import.preview'), [
            'entity' => 'mileage-logs',
            'csv_file' => $file,
            'import_year' => 2025,
        ]);

        $response->assertOk();
        $results = $response->viewData('results');

        $january = collect($results)->first(fn ($r) => $r['data']['_label_date'] === '01/2025');
        $february = collect($results)->first(fn ($r) => $r['data']['_label_date'] === '02/2025');

        $this->assertNotNull($january);
        $this->assertTrue($january['data']['_exists']);
        $this->assertFalse($january['valid']);

        $this->assertNotNull($february);
        $this->assertFalse($february['data']['_exists']);
        $this->assertTrue($february['valid']);
    }

    public function test_confirm_rejects_vehicle_id_from_another_group(): void
    {
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);
        $userA = User::factory()->create();
        $groupA->addUser($userA, Group::ROLE_CAPO);

        $vt = VehicleType::create(['name' => 'Ambulanza', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $vehicleB = Vehicle::create([
            'license_plate' => 'EF456GH',
            'vehicle_type_id' => $vt->id,
            'internal_code' => '0002',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $groupB->id,
        ]);

        $this->actingAs($userA)->post(route('admin.csv-import.confirm'), [
            'entity' => 'issues',
            'editable' => [
                [
                    '_valid' => '1',
                    '_vehicle_id' => $vehicleB->id,
                    '_description' => 'Guasto indebito',
                    '_date' => '2025-01-01',
                    '_status' => 'open',
                ],
            ],
        ]);

        $this->assertDatabaseCount('issues', 0);

        $this->actingAs($userA)->post(route('admin.csv-import.confirm'), [
            'entity' => 'mileage-logs',
            'editable' => [
                [
                    '_valid' => '1',
                    '_vehicle_id' => $vehicleB->id,
                    '_date' => '2025-01-01',
                    '_mileage' => 1000,
                ],
            ],
        ]);

        $this->assertDatabaseCount('mileage_logs', 0);
    }
}
