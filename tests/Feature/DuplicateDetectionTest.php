<?php
namespace Tests\Feature;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class DuplicateDetectionTest extends TestCase
{
    use RefreshDatabase;
    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
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
    public function test_duplicate_maintenance_is_blocked(): void
    {
        $u = $this->admin();
        $v = $this->vehicle();
        $p = $this->provider();
        $issue = Issue::create(['vehicle_id' => $v->id, 'description' => 'Test', 'status' => 'open', 'event_date' => '2025-01-01']);
        $this->actingAs($u)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $v->id, 'provider_id' => $p->id, 'issue_ids' => [$issue->id], 'appointment_date' => '2025-02-01',
        ]);
        $this->assertEquals(1, MaintenanceRecord::count());
        $this->actingAs($u)->post(route('admin.maintenance-records.store'), [
            'vehicle_id' => $v->id, 'provider_id' => $p->id, 'issue_ids' => [$issue->id], 'appointment_date' => '2025-02-01',
        ]);
        $this->assertEquals(1, MaintenanceRecord::count());
    }
    public function test_duplicate_issue_is_blocked(): void
    {
        $u = $this->admin(); $v = $this->vehicle();
        $this->actingAs($u)->post(route('admin.issues.store'), ['vehicle_id' => $v->id, 'description' => 'Test dup', 'event_date' => '2025-01-01', 'status' => 'open']);
        $this->assertEquals(1, Issue::count());
        $this->actingAs($u)->post(route('admin.issues.store'), ['vehicle_id' => $v->id, 'description' => 'Test dup', 'event_date' => '2025-01-01', 'status' => 'open']);
        $this->assertEquals(1, Issue::count());
    }
    public function test_duplicate_provider_is_blocked(): void
    {
        $u = $this->admin();
        $this->actingAs($u)->post(route('admin.providers.store'), ['name' => 'Officina X', 'type' => 'Meccanico']);
        $this->assertEquals(1, Provider::count());
        $this->actingAs($u)->post(route('admin.providers.store'), ['name' => 'Officina X', 'type' => 'Meccanico']);
        $this->assertEquals(1, Provider::count());
    }
    public function test_different_maintenance_is_not_blocked(): void
    {
        $u = $this->admin(); $v = $this->vehicle(); $p = $this->provider();
        $this->actingAs($u)->post(route('admin.maintenance-records.store'), ['vehicle_id' => $v->id, 'provider_id' => $p->id, 'appointment_date' => '2025-02-01']);
        $this->assertEquals(1, MaintenanceRecord::count());
        $v2 = Vehicle::create(['license_plate' => 'XY999ZZ', 'vehicle_type_id' => $v->vehicle_type_id, 'internal_code' => '5678', 'brand_id' => null, 'car_model_id' => null, 'fuel_type' => 'diesel', 'immatricolation_date' => '2024-01-01']);
        $this->actingAs($u)->post(route('admin.maintenance-records.store'), ['vehicle_id' => $v2->id, 'provider_id' => $p->id, 'appointment_date' => '2025-02-01']);
        $this->assertEquals(2, MaintenanceRecord::count());
    }
}
