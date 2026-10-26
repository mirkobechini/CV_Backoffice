<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\Tire;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceRecordTireUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    private function createVehicle(): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ]);
    }

    private function createProvider(): Provider
    {
        return Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);
    }

    private function createTire(Vehicle $vehicle, array $overrides = []): Tire
    {
        return Tire::create(array_merge([
            'vehicle_id' => $vehicle->id,
            'season' => 'winter',
            'position' => Tire::POSITION_FRONT_LEFT,
            'status' => 'stored',
        ], $overrides));
    }

    public function test_updating_appointment_with_return_date_mounts_linked_tire(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $oldTire = $this->createTire($vehicle, ['status' => 'mounted']);
        $newTire = $this->createTire($vehicle, ['season' => 'summer', 'status' => 'stored']);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => 'Cambio Gomme',
            'mileage_at_service' => 42000,
        ]);
        $maintenance->items()->create([
            'itemable_id' => $newTire->id,
            'itemable_type' => Tire::class,
            'completed' => false,
        ]);

        $response = $this->actingAs($user)->put(route('admin.maintenance-records.update', $maintenance), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => $maintenance->appointment_date->toDateString(),
            'return_date' => today()->toDateString(),
            'activity_type' => 'Cambio Gomme',
            'mileage_at_service' => 42000,
            'target_tire_ids' => [$newTire->id],
            'previous_disposition' => [$newTire->id => 'stored'],
        ]);

        $response->assertRedirect(route('admin.maintenance-records.show', $maintenance));
        $this->assertSame('mounted', $newTire->fresh()->status);
        $this->assertSame('stored', $oldTire->fresh()->status);
        $this->assertDatabaseHas('maintenance_record_items', [
            'maintenance_record_id' => $maintenance->id,
            'itemable_id' => $newTire->id,
            'completed' => true,
        ]);
    }

    public function test_updating_appointment_with_return_date_requires_tire_disposition_choice(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $oldTire = $this->createTire($vehicle, ['status' => 'mounted']);
        $newTire = $this->createTire($vehicle, ['season' => 'summer', 'status' => 'stored']);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => 'Cambio Gomme',
            'mileage_at_service' => 42000,
        ]);
        $maintenance->items()->create([
            'itemable_id' => $newTire->id,
            'itemable_type' => Tire::class,
            'completed' => false,
        ]);

        $response = $this->actingAs($user)->put(route('admin.maintenance-records.update', $maintenance), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => $maintenance->appointment_date->toDateString(),
            'return_date' => today()->toDateString(),
            'activity_type' => 'Cambio Gomme',
            'mileage_at_service' => 42000,
            'target_tire_ids' => [$newTire->id],
        ]);

        $response->assertSessionHasErrors('previous_disposition');
        $this->assertSame('stored', $newTire->fresh()->status);
        $this->assertDatabaseHas('maintenance_record_items', [
            'maintenance_record_id' => $maintenance->id,
            'itemable_id' => $newTire->id,
            'completed' => false,
        ]);
    }

    public function test_completing_appointment_without_tire_item_does_not_require_disposition(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => 'Tagliando',
        ]);

        $response = $this->actingAs($user)->patch(route('admin.maintenance-records.complete', $maintenance), [
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
    }

    public function test_edit_page_shows_tire_disposition_question_for_replaced_tire(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();
        $this->createTire($vehicle, ['status' => 'mounted']);
        $newTire = $this->createTire($vehicle, ['season' => 'summer', 'status' => 'stored']);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => 'Cambio Gomme',
        ]);
        $maintenance->items()->create([
            'itemable_id' => $newTire->id,
            'itemable_type' => Tire::class,
            'completed' => false,
        ]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.edit', $maintenance));

        $response->assertOk();
        $response->assertSee('Gomme sostituite');
        $response->assertSee('Va in magazzino');
        $response->assertSee('Viene dismessa');
    }
}
