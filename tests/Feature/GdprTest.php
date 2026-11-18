<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Notification;
use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class GdprTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_export_personal_data(): void
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($user, Group::ROLE_CAPO);
        Notification::create(['user_id' => $user->id, 'type' => 'system', 'title' => 'Test']);

        $response = $this->actingAs($user)->get('/profile/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');

        $data = json_decode($response->getContent(), true);
        $this->assertEquals($user->email, $data['user']['email']);
        $this->assertCount(1, $data['groups']);
        $this->assertEquals('Gruppo A', $data['groups'][0]['name']);
        $this->assertCount(1, $data['notifications']);
        $this->assertFalse($data['user']['two_factor_enabled']);
    }

    /**
     * Audit sicurezza SL6: l'export omette impostazioni di notifica
     * (incluso il collegamento Telegram), token API, voci activity log,
     * stato 2FA.
     */
    public function test_export_includes_notification_settings_api_tokens_and_activity_log(): void
    {
        $user = User::factory()->create();
        NotificationSetting::create(['user_id' => $user->id, 'key' => 'telegram_chat_id', 'value' => '12345']);
        NotificationSetting::create(['user_id' => $user->id, 'key' => 'telegram_link_token', 'value' => 'SECRET|999999']);
        $user->createToken('App mobile');
        Activity::create([
            'log_name' => 'default',
            'description' => 'test-activity-marker',
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/profile/export');

        $data = json_decode($response->getContent(), true);

        $settingKeys = array_column($data['notification_settings'], 'key');
        $this->assertContains('telegram_chat_id', $settingKeys);
        $this->assertNotContains('telegram_link_token', $settingKeys);

        $this->assertCount(1, $data['api_tokens']);
        $this->assertEquals('App mobile', $data['api_tokens'][0]['name']);

        $this->assertCount(1, $data['activity_log']);
        $this->assertEquals('test-activity-marker', $data['activity_log'][0]['description']);
    }

    public function test_capo_can_transfer_role_before_deleting_account(): void
    {
        $capo = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);
        $group->addUser($member, Group::ROLE_MEMBER);

        $response = $this->actingAs($capo)->delete('/profile', [
            'password' => 'password',
            "successor_{$group->id}" => $member->id,
        ]);

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull($capo->fresh());

        // Il ruolo capo è stato trasferito al membro
        $this->assertEquals(Group::ROLE_CAPO, $member->roleIn($group->fresh()));
    }

    public function test_capo_can_delete_account_without_transfer(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);

        $response = $this->actingAs($capo)->delete('/profile', [
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull($capo->fresh());
    }
}
