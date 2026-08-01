<?php

namespace Tests\Feature;

use App\Models\Deadline;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function worker(): User
    {
        return User::factory()->create(['role' => 'worker']);
    }

    private function vehicle(): Vehicle
    {
        $vt = VehicleType::create(['name' => 'Ambulanza', 'needs_oxygen_check' => true, 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        return Vehicle::create(['license_plate' => 'AB123CD', 'vehicle_type_id' => $vt->id, 'internal_code' => '1234', 'brand_id' => null, 'car_model_id' => null, 'fuel_type' => 'diesel', 'immatricolation_date' => '2024-01-01']);
    }

    private function provider(): Provider
    {
        return Provider::create(['name' => 'Test', 'contact_info' => '+39 123', 'address' => 'Via R', 'type' => 'Meccanico']);
    }

    public function test_worker_gets_403_on_post(): void
    {
        $v = $this->vehicle();
        $p = $this->provider();
        $this->actingAs($this->worker())
            ->post(route('admin.vehicles.store'), ['license_plate' => 'XY000ZZ', 'vehicle_type_id' => $v->vehicle_type_id, 'internal_code' => '1234', 'brand_id' => null, 'car_model_id' => null, 'fuel_type' => 'diesel', 'immatricolation_date' => '2024-01-01'])
            ->assertForbidden();
    }

    public function test_worker_gets_403_on_issue_store(): void
    {
        $this->actingAs($this->worker())
            ->post(route('admin.issues.store'), ['vehicle_id' => $this->vehicle()->id, 'description' => 'test', 'event_date' => '2025-01-01'])
            ->assertForbidden();
    }

    public function test_worker_gets_403_on_maintenance_store(): void
    {
        $this->actingAs($this->worker())
            ->post(route('admin.maintenance-records.store'), ['vehicle_id' => $this->vehicle()->id, 'provider_id' => $this->provider()->id, 'appointment_date' => '2025/02/01'])
            ->assertForbidden();
    }

    public function test_worker_gets_403_on_deadline_store(): void
    {
        $this->actingAs($this->worker())
            ->post(route('admin.deadlines.store'), ['vehicle_id' => $this->vehicle()->id, 'type' => Deadline::TYPE_MINISTERIAL, 'due_date' => '2025-06-01'])
            ->assertForbidden();
    }

    public function test_worker_gets_403_on_provider_store(): void
    {
        $this->actingAs($this->worker())
            ->post(route('admin.providers.store'), ['name' => 'X', 'type' => 'Meccanico'])
            ->assertForbidden();
    }

    public function test_worker_can_view_index(): void
    {
        $u = $this->worker();
        $this->actingAs($u)->get(route('admin.vehicles.index'))->assertStatus(200);
        $this->actingAs($u)->get(route('admin.issues.index'))->assertStatus(200);
        $this->actingAs($u)->get(route('admin.maintenance-records.index'))->assertStatus(200);
        $this->actingAs($u)->get(route('admin.deadlines.index'))->assertStatus(200);
        $this->actingAs($u)->get(route('dashboard'))->assertStatus(200);
    }
}
