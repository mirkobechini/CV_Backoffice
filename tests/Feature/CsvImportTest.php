<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
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
        return User::factory()->create(['role' => 'admin']);
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
}
