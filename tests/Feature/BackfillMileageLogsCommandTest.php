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
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2024-05-15',
            'last_mileage' => 60000,
            'is_renewed' => true,
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
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2024-05-15',
            'last_mileage' => 60000,
            'is_renewed' => true,
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
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2024-01-01',
            'last_mileage' => 90000,
            'is_renewed' => true,
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
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2024-05-15',
            'last_mileage' => 60000,
            'is_renewed' => true,
        ]);
        Deadline::create([
            'vehicle_id' => $vehicleB->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2024-05-15',
            'last_mileage' => 70000,
            'is_renewed' => true,
        ]);

        $this->artisan("mileage-logs:backfill --vehicle={$vehicleA->id} --apply")->assertExitCode(0);

        $this->assertDatabaseHas('mileage_logs', ['vehicle_id' => $vehicleA->id, 'mileage' => 60000]);
        $this->assertDatabaseMissing('mileage_logs', ['vehicle_id' => $vehicleB->id, 'mileage' => 70000]);
    }

    public function test_zero_mileage_is_never_registered(): void
    {
        // VehicleObserver crea le scadenze iniziali di tagliando/cinghia con
        // last_mileage=0 come segnaposto "non ancora noto", non un vero 0
        // km: registrarlo come lettura vera produrrebbe solo falsi
        // conflitti con la cronologia reale.
        $vehicle = $this->vehicle();
        $provider = Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2024-05-15',
            'return_date' => '2024-05-15',
            'mileage_at_service' => 0,
        ]);

        $this->artisan('mileage-logs:backfill --apply')->assertExitCode(0);

        $this->assertDatabaseCount('mileage_logs', 0);
    }

    public function test_tagliando_and_cinghia_deadlines_are_never_used_as_candidates(): void
    {
        // last_mileage su tagliando/cinghia è il km dell'ULTIMO cambio (una
        // lettura passata), mentre due_date è la data FUTURA in cui la
        // prossima scadenza è prevista: abbinarli produrrebbe una lettura
        // falsa. La loro lettura reale arriva solo dall'appuntamento che le
        // genera (già coperto dagli altri test tramite MaintenanceRecord).
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => '2025-06-15',
            'last_mileage' => 60000,
            'interval_km' => 20000,
        ]);
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
            'due_date' => '2030-06-15',
            'last_mileage' => 60000,
            'interval_km' => 100000,
        ]);

        $this->artisan('mileage-logs:backfill')
            ->expectsOutputToContain('Nessuna lettura km da registrare.')
            ->assertExitCode(0);
    }

    public function test_interactive_conflict_can_be_forced(): void
    {
        $vehicle = $this->vehicle();
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2024-02-01', 'mileage' => 50000]);

        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2024-01-01',
            'last_mileage' => 90000,
            'is_renewed' => true,
        ]);

        $this->artisan('mileage-logs:backfill --apply --interactive')
            ->expectsQuestion('Come vuoi procedere?', 'Registrala comunque (ignora la cronologia)')
            ->assertExitCode(0);

        $this->assertDatabaseHas('mileage_logs', [
            'vehicle_id' => $vehicle->id,
            'log_date' => '2024-01-01 00:00:00',
            'mileage' => 90000,
        ]);
    }

    public function test_interactive_conflict_can_be_resolved_with_a_different_value(): void
    {
        $vehicle = $this->vehicle();
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2024-02-01', 'mileage' => 50000]);

        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2024-01-01',
            'last_mileage' => 90000,
            'is_renewed' => true,
        ]);

        $this->artisan('mileage-logs:backfill --apply --interactive')
            ->expectsQuestion('Come vuoi procedere?', 'Inserisci un valore km diverso')
            ->expectsQuestion('Nuovo valore km per questa data', '45000')
            ->assertExitCode(0);

        $this->assertDatabaseHas('mileage_logs', [
            'vehicle_id' => $vehicle->id,
            'log_date' => '2024-01-01 00:00:00',
            'mileage' => 45000,
        ]);
        $this->assertDatabaseMissing('mileage_logs', ['mileage' => 90000]);
    }
}
