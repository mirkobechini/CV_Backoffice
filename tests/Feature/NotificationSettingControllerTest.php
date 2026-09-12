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
        return User::factory()->withRole('admin')->create();
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

    public function test_member_can_update_own_notification_settings(): void
    {
        // Le impostazioni di notifica sono personali per account: a
        // differenza dei dati di gruppo, un membro base può gestire le
        // proprie senza dover essere capo/sottocapo.
        $member = User::factory()->withRole('member')->create();

        $this->actingAs($member)
            ->patch(route('admin.notifications.update'), [
                'report_email' => 'member@example.com',
                'report_frequency' => 'daily',
                'reminder_days_before' => 7,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('notification_settings', [
            'user_id' => $member->id,
            'key' => 'report_email',
            'value' => 'member@example.com',
        ]);
    }
}
