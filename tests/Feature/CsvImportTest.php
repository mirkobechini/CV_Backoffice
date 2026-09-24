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
