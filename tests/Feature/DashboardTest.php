<?php

namespace Tests\Feature;

use App\Models\Deadline;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_loads_for_authenticated_user(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_requires_auth(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_dashboard_shows_expired_deadlines(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand' => 'Fiat',
            'model' => 'Ducato',
            'immatricolation_date' => now()->subYears(2),
        ]);
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => now()->subDays(10),
            'status' => Deadline::STATUS_EXPIRED,
            'is_renewed' => false,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Scadenze scadute e non rinnovate');
        $response->assertSee('Tagliando');
    }
}
