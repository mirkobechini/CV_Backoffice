<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_activity_log_index_loads(): void
    {
        $user = $this->admin();
        activity()->causedBy($user)->log('Test activity');

        $response = $this->actingAs($user)->get(route('admin.activity-log.index'));
        $response->assertOk();
    }

    public function test_activity_log_filters_by_log_name(): void
    {
        $user = $this->admin();
        activity()->causedBy($user)->log('Vehicle activity');
        activity()->causedBy($user)->log('Issue activity');

        $response = $this->actingAs($user)->get(route('admin.activity-log.index', ['log_name' => 'default']));
        $response->assertOk();
    }

    public function test_activity_log_requires_auth(): void
    {
        $response = $this->get(route('admin.activity-log.index'));
        $response->assertRedirect(route('login'));
    }
}
