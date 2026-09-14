<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\MaintenanceRecord;
use App\Models\MileageLog;
use App\Models\Provider;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillMileageLogsCommandTest extends TestCase
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

    public function test_dry_run_reports_candidates_without_changing_the_database(): void
    {
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => '2024-05-15',
            'last_mileage' => 60000,
            'interval_km' => 20000,
        ]);

        $this->artisan('mileage-logs:backfill')
            ->expectsOutputToContain('Da registrare')
            ->assertExitCode(0);

        $this->assertDatabaseCount('mileage_logs', 0);
    }

    public function test_apply_creates_logs_from_deadlines_and_maintenance_records(): void
    {
        $vehicle = $this->vehicle();
        $provider = Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);

        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => '2024-05-15',
            'last_mileage' => 60000,
            'interval_km' => 20000,
        ]);

        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2024-08-01',
            'return_date' => '2024-08-01',
            'mileage_at_service' => 65000,
        ]);

        $this->artisan('mileage-logs:backfill --apply')->assertExitCode(0);

        $this->assertDatabaseHas('mileage_logs', ['vehicle_id' => $vehicle->id, 'mileage' => 60000]);
        $this->assertDatabaseHas('mileage_logs', ['vehicle_id' => $vehicle->id, 'mileage' => 65000]);
        $this->assertDatabaseCount('mileage_logs', 2);
    }

    public function test_apply_skips_a_reading_that_conflicts_with_existing_chronology(): void
    {
        $vehicle = $this->vehicle();
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2024-02-01', 'mileage' => 50000]);

        // Data precedente ma km superiore alla lettura già registrata dopo:
        // incoerente, va scartata.
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => '2024-01-01',
            'last_mileage' => 90000,
            'interval_km' => 20000,
        ]);

        $this->artisan('mileage-logs:backfill --apply')->assertExitCode(0);

        $this->assertDatabaseMissing('mileage_logs', ['mileage' => 90000]);
        $this->assertDatabaseCount('mileage_logs', 1);
    }

    public function test_apply_respects_the_vehicle_option(): void
    {
        $vehicleA = $this->vehicle();
        $vehicleB = $this->vehicle();

        Deadline::create([
            'vehicle_id' => $vehicleA->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => '2024-05-15',
            'last_mileage' => 60000,
            'interval_km' => 20000,
        ]);
        Deadline::create([
            'vehicle_id' => $vehicleB->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => '2024-05-15',
            'last_mileage' => 70000,
            'interval_km' => 20000,
        ]);

        $this->artisan("mileage-logs:backfill --vehicle={$vehicleA->id} --apply")->assertExitCode(0);

        $this->assertDatabaseHas('mileage_logs', ['vehicle_id' => $vehicleA->id, 'mileage' => 60000]);
        $this->assertDatabaseMissing('mileage_logs', ['vehicle_id' => $vehicleB->id, 'mileage' => 70000]);
    }
}
