<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    public function test_activity_log_index_loads(): void
    {
        $user = $this->admin();
        activity()->causedBy($user)->log('Test activity');

        $response = $this->actingAs($user)->get(route('admin.activity-log.index'));
        $response->assertOk();
    }

    public function test_activity_log_filters_by_subject_type(): void
    {
        // log_name è sempre "default" per tutti i modelli: il filtro reale
        // per tipo di elemento si basa su subject_type, non su log_name
        // (bug corretto: il filtro precedente non aveva alcun effetto).
        $user = $this->admin();

        Activity::create([
            'log_name' => 'default',
            'description' => 'Vehicle activity',
            'subject_type' => Vehicle::class,
            'subject_id' => 1,
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);
        Activity::create([
            'log_name' => 'default',
            'description' => 'Issue activity',
            'subject_type' => Issue::class,
            'subject_id' => 1,
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.activity-log.index', ['subject_type' => Vehicle::class]));

        $response->assertOk();
        $response->assertSee('Vehicle activity');
        $response->assertDontSee('Issue activity');
    }

    public function test_activity_log_requires_auth(): void
    {
        $response = $this->get(route('admin.activity-log.index'));
        $response->assertRedirect(route('login'));
    }
}
