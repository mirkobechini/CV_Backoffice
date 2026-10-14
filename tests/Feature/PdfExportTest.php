<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Group;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\MileageLog;
use App\Models\Provider;
use App\Models\Tire;
use App\Models\TireChange;
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
        // Stesso gruppo creato dallo stato "admin" di UserFactory::withRole():
        // il veicolo deve appartenervi per essere accessibile (route scoperta
        // per gruppo).
        $group = Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => Carbon::today()->subYears(2),
            'group_id' => $group->id,
        ]);
        // Copre anche la sezione pneumatici del PDF (vedi
        // resources/views/pdfs/scheda-veicolo.blade.php): senza una gomma
        // registrata, un eventuale errore negli accessor usati lì (position_label,
        // season_label, ecc.) non verrebbe mai esercitato da questo test.
        $oldTire = Tire::create([
            'vehicle_id' => $vehicle->id,
            'season' => Tire::SEASON_SUMMER,
            'position' => Tire::POSITION_FRONT_LEFT,
            'status' => Tire::STATUS_STORED,
        ]);
        $newTire = Tire::create([
            'vehicle_id' => $vehicle->id,
            'season' => Tire::SEASON_WINTER,
            'position' => Tire::POSITION_FRONT_LEFT,
            'status' => Tire::STATUS_MOUNTED,
        ]);
        // Copre anche lo storico cambi gomme e la nuova sezione coperture
        // assicurative/parte guasto del PDF: senza queste righe, un errore
        // negli accessor usati lì non verrebbe mai esercitato da questo test.
        TireChange::create([
            'vehicle_id' => $vehicle->id,
            'tire_id' => $newTire->id,
            'previous_tire_id' => $oldTire->id,
            'changed_date' => Carbon::today(),
            'mileage_at_change' => 50000,
        ]);
        $insurance = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_ASSICURAZIONE,
            'due_date' => Carbon::today()->addMonths(6)->toDateString(),
            'insurance_company' => 'Generali',
            'insurance_policy_number' => 'POL-123',
            'status' => 'pending',
        ]);
        $insurance->insuranceCoverages()->create(['coverage_type' => 'RCA', 'cost' => 300]);
        Issue::create([
            'vehicle_id' => $vehicle->id,
            'part' => Issue::PART_FRENI,
            'description' => 'Frena male',
            'status' => 'open',
            'event_date' => Carbon::today(),
        ]);
        $provider = Provider::create(['name' => 'Autolavaggio Test', 'type' => 'Lavaggio']);
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'activity_type' => MaintenanceRecord::ACTIVITY_WASH,
            'appointment_date' => Carbon::today(),
            'appointment_time' => '14:30',
            'return_date' => Carbon::today(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.vehicles.pdf', $vehicle->id));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_fleet_overview_pdf_downloads(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza', 'needs_oxygen_check' => true, 'first_inspection_months' => 12, 'regular_inspection_months' => 12]);
        $group = Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => Carbon::today()->subYears(2),
            'group_id' => $group->id,
            'timing_belt_type' => 'dry_belt',
        ]);

        // Una scadenza per ogni tipo mostrato nella tabella, così ogni
        // branch della cella (data+km / solo data / assente) viene esercitato.
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => Carbon::today()->addMonths(6),
            'interval_km' => 20000,
            'last_mileage' => 10000,
            'status' => 'pending',
        ]);
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => Carbon::today()->addYear(),
            'status' => 'pending',
        ]);
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
            'interval_km' => 100000,
            'last_mileage' => 0,
            'status' => 'pending',
        ]);
        MileageLog::create([
            'vehicle_id' => $vehicle->id,
            'log_date' => Carbon::today(),
            'mileage' => 12345,
        ]);

        $response = $this->actingAs($user)->get(route('admin.vehicles.pdf.fleet-overview'));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
