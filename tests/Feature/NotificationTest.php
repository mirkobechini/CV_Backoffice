<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_notify_user_creates_notification(): void
    {
        $user = $this->admin();
        $service = new NotificationService();

        $notification = $service->notifyUser($user, Notification::TYPE_SYSTEM, 'Test', 'Messaggio', '/dashboard');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test',
            'message' => 'Messaggio',
            'url' => '/dashboard',
            'is_read' => false,
        ]);
        $this->assertEquals($notification->user_id, $user->id);
    }

    public function test_notify_admins_notifies_all_admins(): void
    {
        $admin1 = $this->admin();
        $admin2 = $this->admin();
        $worker = User::factory()->create(['role' => 'worker']);
        $service = new NotificationService();

        $service->notifyAdmins(Notification::TYPE_SYSTEM, 'Test');

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin1->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin2->id]);
        $this->assertDatabaseMissing('notifications', ['user_id' => $worker->id]);
    }

    public function test_notify_with_user_notifies_specific_user(): void
    {
        $user = $this->admin();
        $service = new NotificationService();

        $service->notify($user, Notification::TYPE_SYSTEM, 'Test');

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id]);
    }

    public function test_notify_without_user_notifies_all_admins(): void
    {
        $admin = $this->admin();
        $service = new NotificationService();

        $service->notify(null, Notification::TYPE_SYSTEM, 'Test');

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id]);
    }

    public function test_mark_as_read_updates_notification(): void
    {
        $user = $this->admin();
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Test',
        ]);

        $notification->markAsRead();

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_unread_scope_returns_only_unread(): void
    {
        $user = $this->admin();
        Notification::create(['user_id' => $user->id, 'type' => Notification::TYPE_SYSTEM, 'title' => 'A', 'is_read' => false]);
        Notification::create(['user_id' => $user->id, 'type' => Notification::TYPE_SYSTEM, 'title' => 'B', 'is_read' => true]);

        $unread = Notification::unread()->get();

        $this->assertCount(1, $unread);
        $this->assertEquals('A', $unread->first()->title);
    }
}
