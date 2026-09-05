<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_index_requires_auth(): void
    {
        $response = $this->get(route('notifications.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_index_lists_user_notifications(): void
    {
        $user = $this->admin();
        Notification::create(['user_id' => $user->id, 'type' => Notification::TYPE_SYSTEM, 'title' => 'Test']);

        $response = $this->actingAs($user)->get(route('notifications.index'));
        $response->assertOk();
    }

    public function test_mark_as_read_marks_notification(): void
    {
        $user = $this->admin();
        $notification = Notification::create(['user_id' => $user->id, 'type' => Notification::TYPE_SYSTEM, 'title' => 'Test']);

        $response = $this->actingAs($user)->patch(route('notifications.read', $notification));
        $response->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }

    public function test_mark_as_read_forbidden_for_other_user(): void
    {
        $owner = $this->admin();
        $other = User::factory()->create(['role' => 'worker']);
        $notification = Notification::create(['user_id' => $owner->id, 'type' => Notification::TYPE_SYSTEM, 'title' => 'Test']);

        $response = $this->actingAs($other)->patch(route('notifications.read', $notification));
        $response->assertForbidden();
    }

    public function test_mark_all_as_read(): void
    {
        $user = $this->admin();
        Notification::create(['user_id' => $user->id, 'type' => Notification::TYPE_SYSTEM, 'title' => 'A']);
        Notification::create(['user_id' => $user->id, 'type' => Notification::TYPE_SYSTEM, 'title' => 'B']);

        $response = $this->actingAs($user)->patch(route('notifications.read-all'));
        $response->assertRedirect();

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseMissing('notifications', ['is_read' => false]);
    }
}
