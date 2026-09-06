<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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

    public function test_index_lists_group_users(): void
    {
        [$capo, $group] = $this->capoWithGroup();
        $member = User::factory()->create();
        $group->addUser($member, Group::ROLE_MEMBER);

        $response = $this->actingAs($capo)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee($member->name);
    }

    public function test_capo_can_create_user(): void
    {
        [$capo, $group] = $this->capoWithGroup();

        $response = $this->actingAs($capo)->post(route('admin.users.store'), [
            'name' => 'Nuovo Utente',
            'email' => 'nuovo@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
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

    public function test_member_cannot_create_user(): void
    {
        [$capo, $group] = $this->capoWithGroup();
        $member = User::factory()->create();
        $group->addUser($member, Group::ROLE_MEMBER);

        $response = $this->actingAs($member)->post(route('admin.users.store'), [
            'name' => 'Nuovo Utente',
            'email' => 'nuovo@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'member',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'nuovo@example.com']);
    }

    public function test_capo_can_update_role(): void
    {
        [$capo, $group] = $this->capoWithGroup();
        $member = User::factory()->create();
        $group->addUser($member, Group::ROLE_MEMBER);

        $response = $this->actingAs($capo)->patch(route('admin.users.role', $member), [
            'role' => 'sottocapo',
        ]);

        $response->assertRedirect();
        $this->assertEquals('sottocapo', $member->roleIn($group->fresh()));
    }

    public function test_capo_can_remove_user(): void
    {
        [$capo, $group] = $this->capoWithGroup();
        $member = User::factory()->create();
        $group->addUser($member, Group::ROLE_MEMBER);

        $response = $this->actingAs($capo)->delete(route('admin.users.destroy', $member));

        $response->assertRedirect();
        $this->assertDatabaseMissing('group_user', [
            'user_id' => $member->id,
            'group_id' => $group->id,
        ]);
    }

    public function test_cannot_remove_capo(): void
    {
        [$capo, $group] = $this->capoWithGroup();

        $response = $this->actingAs($capo)->delete(route('admin.users.destroy', $capo));

        $response->assertForbidden();
    }

    public function test_cannot_demote_last_capo(): void
    {
        [$capo, $group] = $this->capoWithGroup();

        $response = $this->actingAs($capo)->patch(route('admin.users.role', $capo), [
            'role' => 'member',
        ]);

        $response->assertForbidden();
    }
}
