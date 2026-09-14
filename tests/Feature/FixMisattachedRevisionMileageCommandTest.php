<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixMisattachedRevisionMileageCommandTest extends TestCase
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

    public function test_dry_run_reports_the_move_without_changing_the_database(): void
    {
        $vehicle = $this->vehicle();
        $parent = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2025-09-30',
            'is_renewed' => true,
            'status' => 'renewed',
        ]);
        $child = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2027-09-30',
            'renews_deadline_id' => $parent->id,
            'last_mileage' => 27058,
        ]);

        $this->artisan('deadlines:fix-misattached-revision-mileage')
            ->expectsOutputToContain('Da correggere')
            ->assertExitCode(0);

        $this->assertDatabaseHas('deadlines', ['id' => $child->id, 'last_mileage' => 27058]);
        $this->assertDatabaseHas('deadlines', ['id' => $parent->id, 'last_mileage' => null]);
    }

    public function test_apply_moves_the_mileage_to_the_renewed_deadline(): void
    {
        $vehicle = $this->vehicle();
        $parent = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2025-09-30',
            'is_renewed' => true,
            'status' => 'renewed',
        ]);
        $child = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2027-09-30',
            'renews_deadline_id' => $parent->id,
            'last_mileage' => 27058,
        ]);

        $this->artisan('deadlines:fix-misattached-revision-mileage --apply')->assertExitCode(0);

        $this->assertDatabaseHas('deadlines', ['id' => $parent->id, 'last_mileage' => 27058]);
        $this->assertDatabaseHas('deadlines', ['id' => $child->id, 'last_mileage' => null]);
    }

    public function test_does_not_overwrite_a_parent_that_already_has_mileage(): void
    {
        $vehicle = $this->vehicle();
        $parent = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2025-09-30',
            'is_renewed' => true,
            'status' => 'renewed',
            'last_mileage' => 50000, // già corretto manualmente
        ]);
        $child = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2027-09-30',
            'renews_deadline_id' => $parent->id,
            'last_mileage' => 27058,
        ]);

        $this->artisan('deadlines:fix-misattached-revision-mileage --apply')
            ->expectsOutputToContain('Nessuna scadenza da correggere.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('deadlines', ['id' => $parent->id, 'last_mileage' => 50000]);
        $this->assertDatabaseHas('deadlines', ['id' => $child->id, 'last_mileage' => 27058]);
    }
}
