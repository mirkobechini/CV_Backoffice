<?php
namespace Tests\Feature;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PdfExportTest extends TestCase
{
    use RefreshDatabase;
    public function test_vehicle_pdf_downloads(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza', 'first_inspection_months' => 12, 'regular_inspection_months' => 12]);
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => Carbon::today()->subYears(2),
        ]);
        $response = $this->actingAs($user)->get(route('admin.vehicles.pdf', $vehicle->id));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
