<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Creazione di un account utente direttamente in un gruppo.
 *
 * La gestione dei membri già esistenti (ruoli, rimozione) è coperta da
 * GroupControllerTest, dato che vive interamente nella pagina del gruppo.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function capoWithGroup(): array
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);

        return [$capo, $group];
    }

    public function test_capo_can_create_user(): void
    {
        [$capo, $group] = $this->capoWithGroup();

        $response = $this->actingAs($capo)->post(route('admin.groups.users.store', $group), [
            'name' => 'Nuovo Utente',
            'email' => 'nuovo@example.com',
            'role' => 'member',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'nuovo@example.com']);
        $this->assertDatabaseHas('group_user', [
            'user_id' => User::where('email', 'nuovo@example.com')->first()->id,
            'group_id' => $group->id,
            'role' => 'member',
        ]);
    }

    public function test_new_user_receives_a_set_password_link_the_capo_never_sees(): void
    {
        [$capo, $group] = $this->capoWithGroup();

        $this->actingAs($capo)->post(route('admin.groups.users.store', $group), [
            'name' => 'Nuovo Utente',
            'email' => 'nuovo@example.com',
            'role' => 'member',
        ]);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'nuovo@example.com']);
    }

    public function test_new_user_also_receives_a_verification_email_immediately(): void
    {
        Notification::fake();
        [$capo, $group] = $this->capoWithGroup();

        $this->actingAs($capo)->post(route('admin.groups.users.store', $group), [
            'name' => 'Nuovo Utente',
            'email' => 'nuovo@example.com',
            'role' => 'member',
        ]);

        $newUser = User::where('email', 'nuovo@example.com')->first();
        Notification::assertSentTo($newUser, VerifyEmail::class);
    }

    public function test_member_cannot_create_user(): void
    {
        [$capo, $group] = $this->capoWithGroup();
        $member = User::factory()->create();
        $group->addUser($member, Group::ROLE_MEMBER);

        $response = $this->actingAs($member)->post(route('admin.groups.users.store', $group), [
            'name' => 'Nuovo Utente',
            'email' => 'nuovo@example.com',
            'role' => 'member',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'nuovo@example.com']);
    }

    public function test_user_not_in_group_cannot_create_user(): void
    {
        [, $group] = $this->capoWithGroup();
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)->post(route('admin.groups.users.store', $group), [
            'name' => 'Nuovo Utente',
            'email' => 'nuovo@example.com',
            'role' => 'member',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'nuovo@example.com']);
    }
}
