<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ActivityLogController;
use App\Models\Issue;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
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

    public function test_property_rows_builds_before_after_diff_for_updates(): void
    {
        $properties = new Collection([
            'old' => ['status' => 'renewed', 'is_renewed' => true, 'updated_at' => '2026-09-12T15:52:21.000000Z'],
            'attributes' => ['status' => 'pending', 'is_renewed' => false, 'updated_at' => '2026-09-12T15:58:52.000000Z'],
        ]);

        $result = ActivityLogController::propertyRows($properties);

        $this->assertSame('diff', $result['mode']);
        // updated_at è un campo tecnico: va escluso dal confronto.
        $this->assertCount(2, $result['rows']);

        $statusRow = collect($result['rows'])->firstWhere('field', 'Stato');
        $this->assertSame('renewed', $statusRow['before']);
        $this->assertSame('pending', $statusRow['after']);

        $renewedRow = collect($result['rows'])->firstWhere('field', 'Rinnovata');
        $this->assertSame('Sì', $renewedRow['before']);
        $this->assertSame('No', $renewedRow['after']);
    }

    public function test_property_rows_shows_flat_values_for_creation(): void
    {
        $properties = new Collection([
            'attributes' => ['name' => 'Ducato', 'created_at' => '2026-09-12T15:52:21.000000Z'],
        ]);

        $result = ActivityLogController::propertyRows($properties);

        $this->assertSame('flat', $result['mode']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('Nome', $result['rows'][0]['field']);
        $this->assertSame('Ducato', $result['rows'][0]['after']);
    }

    public function test_property_rows_handles_empty_properties(): void
    {
        $result = ActivityLogController::propertyRows(new Collection());

        $this->assertSame('flat', $result['mode']);
        $this->assertSame([], $result['rows']);
    }
}
