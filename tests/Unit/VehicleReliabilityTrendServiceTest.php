<?php

namespace Tests\Unit;

use App\Models\Group;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\Vehicle;
use App\Services\VehicleReliabilityTrendService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleReliabilityTrendServiceTest extends TestCase
{
    use RefreshDatabase;

    private function vehicle(): Vehicle
    {
        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand' => 'Fiat',
            'model' => 'Ducato',
            'immatricolation_date' => Carbon::today()->subYears(3),
            'group_id' => Group::firstOrCreate(
                ['name' => 'Associazione di default'],
                ['invite_code' => Group::generateInviteCode()]
            )->id,
        ]);
    }

    private function provider(): Provider
    {
        return Provider::firstOrCreate(['name' => 'Officina Test'], ['type' => 'Meccanico']);
    }

    private function repair(Vehicle $vehicle, string $date): void
    {
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $this->provider()->id,
            'activity_type' => MaintenanceRecord::ACTIVITY_REPAIR,
            'appointment_date' => $date,
        ]);
    }

    public function test_returns_null_with_fewer_than_three_repairs(): void
    {
        $vehicle = $this->vehicle();
        $this->repair($vehicle, '2026-01-01');
        $this->repair($vehicle, '2026-03-01');

        $result = (new VehicleReliabilityTrendService())->analyze($vehicle);

        $this->assertNull($result);
    }

    public function test_computes_average_with_three_repairs_but_no_trend(): void
    {
        $vehicle = $this->vehicle();
        $this->repair($vehicle, '2026-01-01');
        $this->repair($vehicle, '2026-03-02'); // +60 days
        $this->repair($vehicle, '2026-05-01'); // +60 days

        $result = (new VehicleReliabilityTrendService())->analyze($vehicle);

        $this->assertNotNull($result);
        $this->assertSame(3, $result['repairs_count']);
        $this->assertSame(60, $result['average_interval_days']);
        $this->assertSame(60, $result['last_interval_days']);
        $this->assertFalse($result['is_worsening']);
    }

    public function test_flags_worsening_when_last_interval_is_much_shorter(): void
    {
        $vehicle = $this->vehicle();
        $this->repair($vehicle, '2026-01-01');
        $this->repair($vehicle, '2026-03-02'); // +60 days
        $this->repair($vehicle, '2026-05-01'); // +60 days
        $this->repair($vehicle, '2026-05-11'); // +10 days: molto più corto

        $result = (new VehicleReliabilityTrendService())->analyze($vehicle);

        $this->assertSame(10, $result['last_interval_days']);
        $this->assertTrue($result['is_worsening']);
    }

    public function test_does_not_flag_worsening_when_last_interval_is_similar(): void
    {
        $vehicle = $this->vehicle();
        $this->repair($vehicle, '2026-01-01');
        $this->repair($vehicle, '2026-03-02'); // +60 days
        $this->repair($vehicle, '2026-05-01'); // +60 days
        $this->repair($vehicle, '2026-06-25'); // +55 days: non significativamente diverso

        $result = (new VehicleReliabilityTrendService())->analyze($vehicle);

        $this->assertFalse($result['is_worsening']);
    }

    public function test_ignores_other_activity_types(): void
    {
        $vehicle = $this->vehicle();
        $this->repair($vehicle, '2026-01-01');
        $this->repair($vehicle, '2026-03-01');
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $this->provider()->id,
            'activity_type' => MaintenanceRecord::ACTIVITY_TAGLIANDO,
            'appointment_date' => '2026-04-01',
        ]);

        $result = (new VehicleReliabilityTrendService())->analyze($vehicle);

        // Solo 2 riparazioni vere: sotto soglia.
        $this->assertNull($result);
    }
}
