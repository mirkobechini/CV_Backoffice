<?php

namespace Tests\Feature\Crud;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MaintenanceRecordCrudTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User{
        return User::factory()->create();
    }

    private function createVehicle(): Vehicle
    {
        $brand = Brand::create([
            'name' => 'Fiat',
        ]);

        $carModel = CarModel::create([
            'name' => 'Ducato',
            'brand_id' => $brand->id,
        ]);

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
        ]);

    }

    private function createProvider(): Provider
    {
        return Provider::create([
            'name' => "boh",
            'contact_info' => "+39 3885245",
            'address' => 'Via roma 2, Milano',
            'type' => 'Meccanico',
        ]);
    }

    private function createIssue() : array  {
        $vehicle = $this->createVehicle();

        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'something',
            'status' => 'closed',
            'event_date' => '2025-01-02',
        ]);

        return (compact('vehicle', 'issue'));
    }

    private function createMaintenance(): array
    {
        
        $data = $this->createIssue();
        $vehicle = $data['vehicle'];
        $issue = $data['issue'];
        $provider = $this->createProvider();

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'issue_id' => $issue->id,
            'appointment_date' => '2025/01/03'
            ]
        );

        return compact('vehicle', 'provider', 'issue', 'maintenance');
    }

    public function test_maintenance_index_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.index'));

        $response->assertStatus(200);
    }


    public function test_maintenance_create_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.create'));

        $response->assertStatus(200);
    }



    public function test_maintenance_show_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $maintenance = $this->createMaintenance()['maintenance'];

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.show', $maintenance));

        $response->assertStatus(200);
    }

    public function test_maintenance_edit_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $maintenance = $this->createMaintenance()['maintenance'];

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.edit', $maintenance));

        $response->assertStatus(200);
    }


    public function test_maintenance_can_be_stored(): void
    {
        $user = $this->createUser();    //fake user
        $provider = $this->createProvider();
        $issueData = $this->createIssue();
        $vehicle = $issueData['vehicle'];
        $issue = $issueData['issue'];

        $response = $this->actingAs($user)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'issue_id' => $issue->id,
            'appointment_date' => '2025/01/03'
        ]);

        $maintenance = MaintenanceRecord::first();

        $response->assertRedirect(route('admin.maintenance-records.show', $maintenance));
    }


    public function test_maintenance_can_be_updated(): void
    {
       $user = $this->createUser();
        $data = $this->createMaintenance();
        $maintenance = $data['maintenance'];
        $vehicle = $data['vehicle'];
        $issue = $data['issue'];
        $provider = $data['provider'];

        $response = $this->actingAs($user)->put(route('admin.maintenance-records.update', $maintenance), [
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'issue_id' => $issue->id,
            'appointment_date' => '2026/01/03'
        ]);

        $response->assertRedirect(route('admin.maintenance-records.show', $maintenance));

    }

    public function test_maintenance_can_be_deleted(): void
    {
       $user = $this->createUser();

        $maintenance = $this->createMaintenance()['maintenance'];

        $response = $this->actingAs($user)->delete(route('admin.maintenance-records.destroy', $maintenance));

        $response->assertRedirect(route('admin.maintenance-records.index'));
        $this->assertDatabaseMissing('maintenance_records', [
            'id' => $maintenance->id,
        ]);
    }
}
