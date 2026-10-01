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

    // --- Test di caratterizzazione aggiunti prima del refactor verso
    // CsvImporter (issue-72): fissano il comportamento attuale come rete
    // di sicurezza, la copertura esistente (sopra) non bastava per un'area
    // a questo rischio.

    public function test_preview_parses_mileage_logs_simple_format(): void
    {
        $vehicle = $this->vehicle();
        $csv = "veicolo,mese,chilometri\n1234,03/2025,15000\n";
        $file = UploadedFile::fake()->createWithContent('simple.csv', $csv);

        $response = $this->actingAs($this->admin())->post(route('admin.csv-import.preview'), [
            'entity' => 'mileage-logs',
            'csv_file' => $file,
        ]);

        $response->assertOk();
        $results = $response->viewData('results');

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['valid']);
        $this->assertSame($vehicle->id, $results[0]['data']['_vehicle_id']);
        $this->assertSame(15000, $results[0]['data']['_mileage']);
        $this->assertSame('2025-03-01', $results[0]['data']['_date']);
    }

    public function test_preview_mileage_logs_simple_format_flags_invalid_rows(): void
    {
        $this->vehicle();
        $csv = "veicolo,mese,chilometri\nSCONOSCIUTO,03/2025,15000\n1234,13/2025,15000\n1234,03/2025,abc\n";
        $file = UploadedFile::fake()->createWithContent('simple.csv', $csv);

        $response = $this->actingAs($this->admin())->post(route('admin.csv-import.preview'), [
            'entity' => 'mileage-logs',
            'csv_file' => $file,
        ]);

        $results = $response->viewData('results');

        $this->assertFalse($results[0]['valid']); // veicolo non trovato
        $this->assertFalse($results[1]['valid']); // mese 13 non valido
        $this->assertFalse($results[2]['valid']); // chilometri non numerici
    }

    public function test_confirm_imports_valid_mileage_log(): void
    {
        $vehicle = $this->vehicle();

        $this->actingAs($this->admin())->post(route('admin.csv-import.confirm'), [
            'entity' => 'mileage-logs',
            'editable' => [
                [
                    '_valid' => '1',
                    '_vehicle_id' => $vehicle->id,
                    '_date' => '2025-03-01',
                    '_label_date' => '03/2025',
                    '_mileage' => 15000,
                ],
            ],
        ]);

        $this->assertDatabaseHas('mileage_logs', [
            'vehicle_id' => $vehicle->id,
            'mileage' => 15000,
        ]);
    }

    public function test_preview_issue_parses_iso_and_slash_date_formats(): void
    {
        $this->vehicle();
        $csv = "DESCRIZIONE,data\nGuasto A,2025-01-15\nGuasto B,20/02/2025\n";
        $file = UploadedFile::fake()->createWithContent('issues.csv', $csv);

        $response = $this->actingAs($this->admin())->post(route('admin.csv-import.preview'), [
            'entity' => 'issues',
            'csv_file' => $file,
            'vehicle_ref' => '1234',
        ]);

        $results = $response->viewData('results');

        $this->assertSame('2025-01-15', $results[0]['data']['_date']);
        $this->assertSame('2025-02-20', $results[1]['data']['_date']);
    }

    public function test_confirm_rejects_duplicate_mileage_log_even_if_marked_valid(): void
    {
        // whereDate() invece di where(): prima un confronto di uguaglianza
        // esatta su stringa non rilevava mai il duplicato se la colonna
        // conteneva un suffisso orario.
        $vehicle = $this->vehicle();
        \App\Models\MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2025-03-01', 'mileage' => 9000]);

        $this->actingAs($this->admin())->post(route('admin.csv-import.confirm'), [
            'entity' => 'mileage-logs',
            'editable' => [
                [
                    '_valid' => '1',
                    '_vehicle_id' => $vehicle->id,
                    '_date' => '2025-03-01',
                    '_label_date' => '03/2025',
                    '_mileage' => 15000,
                ],
            ],
        ]);

        $this->assertDatabaseCount('mileage_logs', 1);
        $this->assertDatabaseHas('mileage_logs', ['mileage' => 9000]);
    }

    public function test_confirm_imports_issue_and_detects_duplicate(): void
    {
        // Stesso tema del test precedente, lato guasti.
        $vehicle = $this->vehicle();
        $payload = [
            'entity' => 'issues',
            'editable' => [
                [
                    '_valid' => '1',
                    '_vehicle_id' => $vehicle->id,
                    '_description' => 'Guasto motore',
                    '_date' => '2025-01-15',
                    '_status' => 'open',
                ],
            ],
        ];

        $user = $this->admin();
        $this->actingAs($user)->post(route('admin.csv-import.confirm'), $payload);
        $this->assertDatabaseCount('issues', 1);

        // Stesso import rilanciato: il duplicato va rilevato, non raddoppiato.
        $response = $this->actingAs($user)->post(route('admin.csv-import.confirm'), $payload);
        $this->assertDatabaseCount('issues', 1);
        $response->assertSessionHas('status_errors');
    }

    public function test_confirm_issue_with_unresolved_provider_fails_row_without_crashing(): void
    {
        // Prima di questo fix: provider_id null andava contro il vincolo
        // NOT NULL del DB, l'eccezione non gestita faceva fallire l'intera
        // transazione di confirm() — perdendo anche le altre righe valide
        // nello stesso batch, non solo quella col fornitore sconosciuto.
        $vehicleA = $this->vehicle();

        $response = $this->actingAs($this->admin())->post(route('admin.csv-import.confirm'), [
            'entity' => 'issues',
            'editable' => [
                [
                    '_valid' => '1',
                    '_vehicle_id' => $vehicleA->id,
                    '_description' => 'Guasto A (fornitore sconosciuto)',
                    '_date' => '2025-01-15',
                    '_status' => 'open',
                    '_appointment_date' => '20/01/2025',
                    '_provider_name' => 'Officina Mai Registrata',
                ],
                [
                    '_valid' => '1',
                    '_vehicle_id' => $vehicleA->id,
                    '_description' => 'Guasto B (senza appuntamento)',
                    '_date' => '2025-01-16',
                    '_status' => 'open',
                ],
            ],
        ]);

        $response->assertSessionHas('status_errors');
        $this->assertDatabaseMissing('issues', ['description' => 'Guasto A (fornitore sconosciuto)']);
        $this->assertDatabaseCount('maintenance_records', 0);
        // La riga B, valida e indipendente, non deve essere persa insieme alla A.
        $this->assertDatabaseHas('issues', ['description' => 'Guasto B (senza appuntamento)']);
    }

    public function test_confirm_issue_with_resolved_provider_creates_maintenance_record(): void
    {
        $vehicle = $this->vehicle();
        $provider = \App\Models\Provider::create(['name' => 'Officina Rossi', 'type' => 'Meccanico']);

        $this->actingAs($this->admin())->post(route('admin.csv-import.confirm'), [
            'entity' => 'issues',
            'editable' => [
                [
                    '_valid' => '1',
                    '_vehicle_id' => $vehicle->id,
                    '_description' => 'Guasto motore',
                    '_date' => '2025-01-15',
                    '_status' => 'open',
                    '_appointment_date' => '20/01/2025',
                    '_provider_name' => 'Rossi',
                ],
            ],
        ]);

        $this->assertDatabaseHas('issues', ['description' => 'Guasto motore']);
        $this->assertDatabaseHas('maintenance_records', [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
        ]);
    }
}
