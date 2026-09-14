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

class MileageTimelineCommandTest extends TestCase
{
    use RefreshDatabase;

    private function vehicle(): Vehicle
    {
        $vt = VehicleType::create(['name' => 'Ambulanza', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);

        return Vehicle::create([
            'license_plate' => 'AA111AA',
            'internal_code' => '0001',
            'vehicle_type_id' => $vt->id,
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'immatricolation_date' => '2020-01-01',
        ]);
    }

    public function test_missing_vehicle_reports_an_error(): void
    {
        $this->artisan('mileage-logs:timeline 999')
            ->expectsOutputToContain('Veicolo non trovato.')
            ->assertExitCode(1);
    }

    public function test_lists_readings_in_chronological_order_and_flags_a_drop(): void
    {
        $vehicle = $this->vehicle();
        $provider = Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);

        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2024-01-01', 'mileage' => 50000]);
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2024-06-01',
            'last_mileage' => 40000, // inferiore alla lettura precedente
            'is_renewed' => true,
        ]);
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2024-09-01',
            'return_date' => '2024-09-01',
            'mileage_at_service' => 60000,
        ]);

        $this->artisan("mileage-logs:timeline {$vehicle->id}")
            ->expectsOutputToContain('2024-01-01')
            ->expectsOutputToContain('2024-06-01')
            ->expectsOutputToContain('inferiore alla lettura precedente')
            ->expectsOutputToContain('2024-09-01')
            ->assertExitCode(0);
    }

    public function test_tagliando_and_cinghia_deadlines_are_excluded(): void
    {
        // Il loro last_mileage non è una lettura datata a due_date (vedi
        // mileage-logs:backfill): non deve comparire nella timeline.
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => '2025-01-01',
            'last_mileage' => 30000,
            'interval_km' => 20000,
        ]);

        $this->artisan("mileage-logs:timeline {$vehicle->id}")
            ->expectsOutputToContain('Nessuna lettura km trovata per questo veicolo.')
            ->assertExitCode(0);
    }
}
