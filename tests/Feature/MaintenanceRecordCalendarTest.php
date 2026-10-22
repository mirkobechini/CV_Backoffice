<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceRecordCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function createVehicleWithAppointment(Group $group, string $plate): MaintenanceRecord
    {
        $brand = Brand::firstOrCreate(['name' => 'Fiat']);
        $carModel = CarModel::firstOrCreate(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $vehicleType = VehicleType::firstOrCreate(
            ['name' => 'Ambulanza'],
            ['needs_oxygen_check' => true, 'first_inspection_months' => 48, 'regular_inspection_months' => 24]
        );

        $vehicle = Vehicle::create([
            'license_plate' => $plate,
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => $plate,
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group->id,
        ]);

        $provider = Provider::create([
            'name' => 'Officina '.$plate,
            'type' => 'Meccanico',
        ]);

        return MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2026-06-15',
        ]);
    }

    public function test_calendar_page_loads(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $response = $this->actingAs($user)->get('/admin/maintenance-records/calendar');
        $response->assertStatus(200);
    }

    public function test_vehicles_index_loads(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $response = $this->actingAs($user)->get('/admin/vehicles');
        $response->assertStatus(200);
    }

    public function test_events_endpoint_returns_json(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $response = $this->actingAs($user)->get('/admin/maintenance-records/events?start=2026-01-01&end=2026-12-31');
        $response->assertStatus(200);
        $response->assertJson([]);
    }

    public function test_events_endpoint_does_not_leak_other_groups_appointments(): void
    {
        // Audit sicurezza 2026-10-22 (SH3): il feed non filtrava per
        // gruppo, mostrando gli appuntamenti di TUTTE le associazioni.
        $user = User::factory()->withRole('admin')->create();
        $ownRecord = $this->createVehicleWithAppointment($user->activeGroup(), 'AA111AA');

        $otherGroup = Group::create(['name' => 'Altro Gruppo', 'invite_code' => 'ZZZZ9999']);
        $this->createVehicleWithAppointment($otherGroup, 'BB222BB');

        $response = $this->actingAs($user)->get('/admin/maintenance-records/events?start=2026-01-01&end=2026-12-31');

        $response->assertStatus(200);
        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($ownRecord->id));
        $this->assertCount(1, $ids);
    }
}
