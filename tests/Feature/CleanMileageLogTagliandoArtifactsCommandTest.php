<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\MileageLog;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanMileageLogTagliandoArtifactsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function vehicle(): Vehicle
    {
        static $n = 0;
        $n++;

        $vt = VehicleType::create(['name' => "Ambulanza {$n}", 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $brand = Brand::create(['name' => "Fiat {$n}"]);
        $model = CarModel::create(['name' => "Ducato {$n}", 'brand_id' => $brand->id]);

        return Vehicle::create([
            'license_plate' => sprintf('AA%03dAA', $n),
            'internal_code' => sprintf('%04d', $n),
            'vehicle_type_id' => $vt->id,
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'immatricolation_date' => '2020-01-01',
        ]);
    }

    public function test_dry_run_reports_the_artifact_without_deleting_it(): void
    {
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => '2025-07-18',
            'last_mileage' => 21000,
            'interval_km' => 20000,
        ]);
        $artifact = MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2025-07-18', 'mileage' => 21000]);

        $this->artisan('mileage-logs:clean-tagliando-artifacts')
            ->expectsOutputToContain('Da eliminare')
            ->assertExitCode(0);

        $this->assertDatabaseHas('mileage_logs', ['id' => $artifact->id]);
    }

    public function test_apply_deletes_the_artifact(): void
    {
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => '2025-07-18',
            'last_mileage' => 21000,
            'interval_km' => 20000,
        ]);
        $artifact = MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2025-07-18', 'mileage' => 21000]);

        $this->artisan('mileage-logs:clean-tagliando-artifacts --apply')->assertExitCode(0);

        $this->assertDatabaseMissing('mileage_logs', ['id' => $artifact->id]);
    }

    public function test_a_genuine_reading_that_does_not_match_any_deadline_is_left_alone(): void
    {
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => '2025-07-18',
            'last_mileage' => 21000,
            'interval_km' => 20000,
        ]);
        // Stessa data ma km diverso dalla scadenza: lettura reale, non va toccata.
        $realReading = MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2025-07-18', 'mileage' => 35000]);

        $this->artisan('mileage-logs:clean-tagliando-artifacts --apply')
            ->expectsOutputToContain('Nessun artefatto trovato.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('mileage_logs', ['id' => $realReading->id]);
    }
}
