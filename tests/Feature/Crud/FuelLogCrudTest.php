<?php

namespace Tests\Feature\Crud;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\FuelCard;
use App\Models\FuelLog;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelLogCrudTest extends TestCase
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
            'needs_oxygen_check' => true,
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ]);
    }

    private function createFuelLog(): array
    {
        $vehicle = $this->createVehicle();

        $fuelLog = FuelLog::create([
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-01-25',
            'liters' => '45.50',
            'cost_total' => '68.90',
            'mileage' => 50000,
        ]);

        return compact('vehicle', 'fuelLog');
    }

    public function test_fuel_log_index_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.fuel-logs.index'));

        $response->assertStatus(200);
    }

    public function test_fuel_log_create_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.fuel-logs.create'));

        $response->assertStatus(200);
    }

    public function test_fuel_card_select_shows_vehicle_code_and_sort_data(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $fuelCard = FuelCard::create([
            'card_number' => 'TK-9876',
            'status' => FuelCard::STATUS_ASSIGNED,
            'vehicle_id' => $vehicle->id,
            'group_id' => $vehicle->group_id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.fuel-logs.create'));

        $response->assertStatus(200);
        $response->assertSee('TK-9876 · '.$vehicle->internal_code, false);
        $response->assertSee('data-vehicle-id="'.$fuelCard->vehicle_id.'"', false);
    }

    public function test_fuel_log_show_page_is_reachable(): void
    {
        $user = $this->createUser();
        $fuelLog = $this->createFuelLog()['fuelLog'];

        $response = $this->actingAs($user)->get(route('admin.fuel-logs.show', $fuelLog));

        $response->assertStatus(200);
    }

    public function test_fuel_log_edit_page_is_reachable(): void
    {
        $user = $this->createUser();
        $fuelLog = $this->createFuelLog()['fuelLog'];

        $response = $this->actingAs($user)->get(route('admin.fuel-logs.edit', $fuelLog));

        $response->assertStatus(200);
    }

    public function test_fuel_log_can_be_stored(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.fuel-logs.store'), [
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-01-25',
            'liters' => '45.50',
            'cost_total' => '68.90',
            'mileage' => 50000,
        ]);

        $fuelLog = FuelLog::first();

        $response->assertRedirect(route('admin.fuel-logs.show', $fuelLog));
        $this->assertDatabaseHas('fuel_logs', [
            'id' => $fuelLog->id,
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-01-25 00:00:00',
            'liters' => 45.50,
            'cost_total' => 68.90,
            'mileage' => 50000,
        ]);
    }

    public function test_fuel_log_can_be_stored_with_a_fuel_card(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $fuelCard = FuelCard::create([
            'card_number' => 'FC-001',
            'status' => FuelCard::STATUS_JOLLY,
            'group_id' => $this->defaultGroup()->id,
        ]);

        $response = $this->actingAs($user)->post(route('admin.fuel-logs.store'), [
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-01-25',
            'liters' => '45.50',
            'fuel_card_id' => $fuelCard->id,
        ]);

        $fuelLog = FuelLog::first();
        $response->assertRedirect(route('admin.fuel-logs.show', $fuelLog));
        $this->assertDatabaseHas('fuel_logs', [
            'id' => $fuelLog->id,
            'fuel_card_id' => $fuelCard->id,
        ]);
    }

    public function test_fuel_log_can_be_stored_without_optional_fields(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.fuel-logs.store'), [
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-01-25',
            'liters' => '45.50',
        ]);

        $fuelLog = FuelLog::first();

        $response->assertRedirect(route('admin.fuel-logs.show', $fuelLog));
        $this->assertDatabaseHas('fuel_logs', [
            'id' => $fuelLog->id,
            'cost_total' => null,
            'mileage' => null,
        ]);
    }

    public function test_fuel_log_can_be_updated(): void
    {
        $user = $this->createUser();
        $data = $this->createFuelLog();

        $response = $this->actingAs($user)->put(route('admin.fuel-logs.update', $data['fuelLog']), [
            'vehicle_id' => $data['vehicle']->id,
            'log_date' => '2025-02-10',
            'liters' => '50.00',
            'cost_total' => '75.00',
            'mileage' => 50500,
        ]);

        $response->assertRedirect(route('admin.fuel-logs.show', $data['fuelLog']));
        $this->assertDatabaseHas('fuel_logs', [
            'id' => $data['fuelLog']->id,
            'liters' => 50.00,
            'mileage' => 50500,
        ]);
    }

    public function test_fuel_log_can_be_deleted(): void
    {
        $user = $this->createUser();
        $fuelLog = $this->createFuelLog()['fuelLog'];

        $response = $this->actingAs($user)->delete(route('admin.fuel-logs.destroy', $fuelLog));

        $response->assertRedirect(route('admin.fuel-logs.index'));
        $this->assertDatabaseMissing('fuel_logs', ['id' => $fuelLog->id]);
    }

    public function test_liters_is_required(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.fuel-logs.store'), [
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-01-25',
        ]);

        $response->assertSessionHasErrors('liters');
    }

    public function test_cannot_create_fuel_log_for_another_groups_vehicle(): void
    {
        $user = $this->createUser();
        $otherGroup = Group::create(['name' => 'Altro gruppo', 'invite_code' => Group::generateInviteCode()]);
        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $vehicleType = VehicleType::create(['name' => 'Ambulanza', 'needs_oxygen_check' => true]);
        $otherVehicle = Vehicle::create([
            'license_plate' => 'ZZ999ZZ',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '9999',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $otherGroup->id,
        ]);

        $response = $this->actingAs($user)->post(route('admin.fuel-logs.store'), [
            'vehicle_id' => $otherVehicle->id,
            'log_date' => '2025-01-25',
            'liters' => '45.50',
        ]);

        $response->assertSessionHasErrors('vehicle_id');
    }

    public function test_consumption_is_computed_between_consecutive_logs_with_mileage(): void
    {
        $vehicle = $this->createVehicle();

        $first = FuelLog::create([
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-01-01',
            'liters' => '40.00',
            'mileage' => 50000,
        ]);
        $second = FuelLog::create([
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-02-01',
            'liters' => '40.00',
            'mileage' => 50600,
        ]);

        $consumption = FuelLog::consumptionKmPerLiter($second, $first);

        $this->assertSame(15.0, $consumption);
    }

    public function test_index_computes_consumption_correctly_for_two_refuels_on_the_same_day(): void
    {
        // Audit 2026-10-22 (B7): l'ordinamento del controller usava solo
        // log_date, senza chiave secondaria — con due rifornimenti nello
        // stesso giorno l'ordine tra loro non era deterministico, quindi
        // "precedente" poteva risultare quello sbagliato (km negativi,
        // consumo scartato o calcolato al contrario).
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $first = FuelLog::create([
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-06-10',
            'liters' => '40.00',
            'mileage' => 50000,
        ]);
        $second = FuelLog::create([
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-06-10',
            'liters' => '40.00',
            'mileage' => 50600,
        ]);

        $response = $this->actingAs($user)->get(route('admin.fuel-logs.index'));

        $response->assertOk();
        $response->assertSee('15,00 km/L');
        $this->assertTrue($first->id < $second->id);
    }

    public function test_consumption_is_null_when_mileage_missing(): void
    {
        $vehicle = $this->createVehicle();

        $first = FuelLog::create([
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-01-01',
            'liters' => '40.00',
        ]);
        $second = FuelLog::create([
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-02-01',
            'liters' => '40.00',
        ]);

        $this->assertNull(FuelLog::consumptionKmPerLiter($second, $first));
        $this->assertNull(FuelLog::consumptionKmPerLiter($first, null));
    }
}
