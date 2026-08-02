<?php

namespace Tests\Feature;

use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationSettingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_edit_returns_view(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.notifications.edit'))
            ->assertOk();
    }

    public function test_update_saves_settings(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('admin.notifications.update'), [
                'report_email' => 'test@example.com',
                'report_frequency' => 'daily',
                'reminder_days_before' => 7,
                'notify_on_maintenance' => true,
                'notify_on_deadline' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('notification_settings', [
            'key' => 'report_email',
            'value' => 'test@example.com',
        ]);
        $this->assertDatabaseHas('notification_settings', [
            'key' => 'report_frequency',
            'value' => 'daily',
        ]);
    }

    public function test_worker_cannot_update(): void
    {
        $worker = User::factory()->create(['role' => 'worker']);

        $this->actingAs($worker)
            ->patch(route('admin.notifications.update'), [
                'report_email' => 'test@example.com',
                'report_frequency' => 'daily',
                'reminder_days_before' => 7,
            ])
            ->assertForbidden();
    }
}
