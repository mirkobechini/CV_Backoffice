<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Group;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InspectVehicleDeadlinesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function createVehicle(array $overrides = []): Vehicle
    {
        $group = Group::create(['name' => 'G' . uniqid(), 'invite_code' => Group::generateInviteCode()]);
        $brand = Brand::create(['name' => 'Fiat ' . uniqid()]);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Amb ' . uniqid(), 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);

        return Vehicle::create(array_merge([
            'group_id' => $group->id,
            'license_plate' => 'AB123CD',
            'internal_code' => '1744',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => '2020-01-01',
            'timing_belt_type' => 'dry_belt',
        ], $overrides));
    }

    public function test_finds_deadline_on_a_soft_deleted_duplicate_vehicle(): void
    {
        $oldVehicle = $this->createVehicle();
        Deadline::create([
            'vehicle_id' => $oldVehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
            'status' => 'renewed',
            'is_renewed' => true,
            'due_date' => null,
            'interval_km' => 100000,
            'last_mileage' => 50000,
        ]);
        $oldVehicle->delete();

        $newVehicle = $this->createVehicle(['license_plate' => 'XY987ZZ']);

        Artisan::call('deadlines:inspect', ['vehicle' => '1744']);
        $output = Artisan::output();

        $this->assertStringContainsString('Trovati 2 veicoli', $output);
        $this->assertStringContainsString("#{$oldVehicle->id}", $output);
        $this->assertStringContainsString("#{$newVehicle->id}", $output);
        $this->assertStringContainsString('Cinghia Distribuzione', $output);
    }

    public function test_reports_activity_trace_when_deadline_row_is_fully_gone(): void
    {
        $vehicle = $this->createVehicle();
        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
            'status' => 'renewed',
            'is_renewed' => true,
            'due_date' => null,
            'interval_km' => 100000,
            'last_mileage' => 50000,
        ]);
        $deadline->forceDelete();

        Artisan::call('deadlines:inspect', ['vehicle' => '1744', '--type' => Deadline::TYPE_CINGHIA]);
        $output = Artisan::output();

        $this->assertStringContainsString('registro attività ha tracce', $output);
    }
}
