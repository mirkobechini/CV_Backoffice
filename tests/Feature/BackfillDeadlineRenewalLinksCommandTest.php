<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillDeadlineRenewalLinksCommandTest extends TestCase
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

    public function test_dry_run_reports_links_without_changing_the_database(): void
    {
        $vehicle = $this->vehicle();
        Deadline::where('vehicle_id', $vehicle->id)->forceDelete();

        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2022-09-30',
            'status' => 'renewed',
            'is_renewed' => true,
        ]);
        $newest = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2024-09-30',
            'status' => 'pending',
            'is_renewed' => false,
        ]);

        $this->artisan('deadlines:backfill-renewal-links')
            ->expectsOutputToContain('dry-run')
            ->assertExitCode(0);

        $newest->refresh();
        $this->assertNull($newest->renews_deadline_id);
    }

    public function test_apply_links_the_next_deadline_to_the_previous_one(): void
    {
        $vehicle = $this->vehicle();
        Deadline::where('vehicle_id', $vehicle->id)->forceDelete();

        $oldest = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2020-09-30',
            'status' => 'renewed',
            'is_renewed' => true,
        ]);
        $middle = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2022-09-30',
            'status' => 'renewed',
            'is_renewed' => true,
        ]);
        $current = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2024-09-30',
            'status' => 'pending',
            'is_renewed' => false,
        ]);

        $this->artisan('deadlines:backfill-renewal-links --apply')
            ->assertExitCode(0);

        $middle->refresh();
        $current->refresh();

        $this->assertEquals($oldest->id, $middle->renews_deadline_id);
        $this->assertEquals($middle->id, $current->renews_deadline_id);
    }

    public function test_apply_does_not_overwrite_an_existing_link(): void
    {
        $vehicle = $this->vehicle();
        Deadline::where('vehicle_id', $vehicle->id)->forceDelete();

        $oldest = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2020-09-30',
            'status' => 'renewed',
            'is_renewed' => true,
        ]);
        // Un veicolo diverso, apposta collegato altrove, per verificare che
        // il comando non lo tocchi anche se cronologicamente sarebbe "dopo"
        // un'altra scadenza dello stesso veicolo/tipo.
        $manuallyLinked = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2024-09-30',
            'status' => 'pending',
            'is_renewed' => false,
            'renews_deadline_id' => $oldest->id,
        ]);

        $this->artisan('deadlines:backfill-renewal-links --apply')
            ->assertExitCode(0);

        $manuallyLinked->refresh();
        $this->assertEquals($oldest->id, $manuallyLinked->renews_deadline_id);
    }

    public function test_apply_respects_the_vehicle_option(): void
    {
        $vehicleA = $this->vehicle();
        $vehicleB = $this->vehicle();
        Deadline::whereIn('vehicle_id', [$vehicleA->id, $vehicleB->id])->forceDelete();

        Deadline::create([
            'vehicle_id' => $vehicleA->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2020-09-30',
            'status' => 'renewed',
            'is_renewed' => true,
        ]);
        $nextA = Deadline::create([
            'vehicle_id' => $vehicleA->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2022-09-30',
            'status' => 'pending',
            'is_renewed' => false,
        ]);

        Deadline::create([
            'vehicle_id' => $vehicleB->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2020-09-30',
            'status' => 'renewed',
            'is_renewed' => true,
        ]);
        $nextB = Deadline::create([
            'vehicle_id' => $vehicleB->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => '2022-09-30',
            'status' => 'pending',
            'is_renewed' => false,
        ]);

        $this->artisan("deadlines:backfill-renewal-links --vehicle={$vehicleA->id} --apply")
            ->assertExitCode(0);

        $nextA->refresh();
        $nextB->refresh();

        $this->assertNotNull($nextA->renews_deadline_id);
        $this->assertNull($nextB->renews_deadline_id);
    }
}
