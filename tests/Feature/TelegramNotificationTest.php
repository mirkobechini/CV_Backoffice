<?php

namespace Tests\Feature;

use App\Models\Deadline;
use App\Models\Group;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TelegramNotifier;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    private function vehicle(): Vehicle
    {
        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand' => 'Fiat',
            'model' => 'Ducato',
            'immatricolation_date' => Carbon::today()->subYears(2),
            'group_id' => Group::firstOrCreate(
                ['name' => 'Associazione di default'],
                ['invite_code' => Group::generateInviteCode()]
            )->id,
        ]);
    }

    public function test_start_with_valid_token_links_the_chat(): void
    {
        config(['services.telegram.bot_token' => 'fake-token']);
        Http::fake();

        $user = $this->admin();
        $token = app(TelegramNotifier::class)->generateLinkToken($user);

        $response = $this->postJson(route('telegram.webhook'), [
            'message' => ['chat' => ['id' => 999], 'text' => "/start {$token}"],
        ]);

        $response->assertNoContent();
        $this->assertSame('999', $user->notificationSetting('telegram_chat_id'));
        $this->assertNull($user->fresh()->notificationSettings()->where('key', 'telegram_link_token')->first());
    }

    public function test_start_with_invalid_token_does_not_link_anything(): void
    {
        config(['services.telegram.bot_token' => 'fake-token']);
        Http::fake();

        $this->postJson(route('telegram.webhook'), [
            'message' => ['chat' => ['id' => 999], 'text' => '/start WRONGCODE'],
        ]);

        $this->assertDatabaseMissing('notification_settings', ['key' => 'telegram_chat_id']);
    }

    public function test_start_with_expired_token_does_not_link(): void
    {
        config(['services.telegram.bot_token' => 'fake-token']);
        Http::fake();

        $user = $this->admin();
        NotificationSetting::create([
            'user_id' => $user->id,
            'key' => 'telegram_link_token',
            'value' => 'EXPIRED1|' . now()->subMinute()->timestamp,
        ]);

        $this->postJson(route('telegram.webhook'), [
            'message' => ['chat' => ['id' => 999], 'text' => '/start EXPIRED1'],
        ]);

        $this->assertDatabaseMissing('notification_settings', ['key' => 'telegram_chat_id']);
    }

    public function test_stop_unlinks_the_chat(): void
    {
        config(['services.telegram.bot_token' => 'fake-token']);
        Http::fake();

        $user = $this->admin();
        NotificationSetting::create(['user_id' => $user->id, 'key' => 'telegram_chat_id', 'value' => '999']);

        $this->postJson(route('telegram.webhook'), [
            'message' => ['chat' => ['id' => 999], 'text' => '/stop'],
        ]);

        $this->assertFalse(app(TelegramNotifier::class)->isLinked($user->fresh()));
    }

    public function test_webhook_rejects_request_without_valid_secret(): void
    {
        config(['services.telegram.webhook_secret' => 'my-secret']);

        $response = $this->postJson(route('telegram.webhook'), [
            'message' => ['chat' => ['id' => 999], 'text' => '/stop'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'wrong']);

        $response->assertForbidden();
    }

    public function test_webhook_accepts_request_with_valid_secret(): void
    {
        config(['services.telegram.webhook_secret' => 'my-secret', 'services.telegram.bot_token' => 'fake-token']);
        Http::fake();

        $response = $this->postJson(route('telegram.webhook'), [
            'message' => ['chat' => ['id' => 999], 'text' => '/stop'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'my-secret']);

        $response->assertNoContent();
    }

    public function test_generate_notifications_sends_telegram_message_to_linked_user(): void
    {
        config(['services.telegram.bot_token' => 'fake-token']);
        Http::fake();

        $user = $this->admin();
        NotificationSetting::create(['user_id' => $user->id, 'key' => 'telegram_chat_id', 'value' => '999']);
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => Carbon::today()->addDays(5),
            'status' => Deadline::STATUS_PENDING,
            'is_renewed' => false,
        ]);

        $this->artisan('app:generate-notifications --email');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'fake-token/sendMessage')
            && $request['chat_id'] === '999');
    }

    public function test_generate_notifications_does_not_send_telegram_without_email_flag(): void
    {
        config(['services.telegram.bot_token' => 'fake-token']);
        Http::fake();

        $user = $this->admin();
        NotificationSetting::create(['user_id' => $user->id, 'key' => 'telegram_chat_id', 'value' => '999']);
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => Carbon::today()->addDays(5),
            'status' => Deadline::STATUS_PENDING,
            'is_renewed' => false,
        ]);

        $this->artisan('app:generate-notifications');

        Http::assertNothingSent();
    }

    public function test_user_can_generate_link_token_and_unlink_from_profile(): void
    {
        $user = $this->admin();

        $response = $this->actingAs($user)->post(route('admin.notifications.telegram-link'));
        $response->assertRedirect(route('admin.notifications.edit'));
        $response->assertSessionHas('telegramLinkToken');

        NotificationSetting::create(['user_id' => $user->id, 'key' => 'telegram_chat_id', 'value' => '999']);

        $this->actingAs($user)->delete(route('admin.notifications.telegram-unlink'));
        $this->assertFalse(app(TelegramNotifier::class)->isLinked($user->fresh()));
    }
}
