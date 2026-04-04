<?php

namespace Tests\Feature\Crud;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Issue;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueCrudTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
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

    private function createIssue()   {
        $vehicle = $this->createVehicle();

        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'something',
            'status' => 'closed',
            'event_date' => '2025-01-02',
        ]);
        return (compact("vehicle", 'issue'));
    }

    public function test_issue_index_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.issues.index'));

        $response->assertStatus(200);
    }


    public function test_issue_create_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user

        $response = $this->actingAs($user)->get(route('admin.issues.create'));

        $response->assertStatus(200);
    }



    public function test_issue_show_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $issue = $this->createIssue()['issue'];

        $response = $this->actingAs($user)->get(route('admin.issues.show', $issue));

        $response->assertStatus(200);
    }

    public function test_issue_edit_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $issue = $this->createIssue()['issue'];

        $response = $this->actingAs($user)->get(route('admin.issues.edit', $issue));

        $response->assertStatus(200);
    }


    public function test_issue_can_be_stored(): void
    {
        $user = $this->createUser();    //fake user
        $vehicle = $this->createVehicle();


        $response = $this->actingAs($user)->post(route('admin.issues.store'), [
            'vehicle_id' => $vehicle->id,
            'description' => 'something',
            'status' => 'closed',
            'event_date' => '2025-01-02',
        ]);

        $issue = issue::first();

        $response->assertRedirect(route('admin.issues.show', $issue));

    }


    public function test_issue_can_be_updated(): void
    {
        $user = $this->createUser();
        $data = $this->createIssue();
        $issue = $data['issue'];
        $vehicle = $data['vehicle'];

        $response = $this->actingAs($user)->put(route('admin.issues.update', $issue), [
            'vehicle_id' => $vehicle->id,
            'description' => 'something else',
            'status' => 'open',
            'event_date' => '2026-01-02',
        ]);

        $response->assertRedirect(route('admin.issues.show', $issue));

    }

    public function test_issue_can_be_deleted(): void
    {
        $user = $this->createUser();

        $issue = $this->createIssue()['issue'];

        $response = $this->actingAs($user)->delete(route('admin.issues.destroy', $issue));

        $response->assertRedirect(route('admin.issues.index'));
        $this->assertDatabaseMissing('issues', [
            'id' => $issue->id,
        ]);
    }
}
