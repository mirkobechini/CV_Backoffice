<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceRecordCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_page_loads(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($user)->get('/admin/maintenance-records/calendar');
        $response->assertStatus(200);
    }

    public function test_vehicles_index_loads(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($user)->get('/admin/vehicles');
        $response->assertStatus(200);
    }

    public function test_events_endpoint_returns_json(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($user)->get('/admin/maintenance-records/events?start=2026-01-01&end=2026-12-31');
        $response->assertStatus(200);
        $response->assertJson([]);
    }
}
