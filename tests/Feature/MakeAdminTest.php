<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MakeAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_make_admin_creates_user_and_group_with_capo_role(): void
    {
        $this->artisan('make:admin', [
            '--email' => 'admin@example.com',
            '--password' => 'password',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
        $this->assertDatabaseHas('groups', ['name' => 'Associazione di default']);

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->isCapo());
        $this->assertNotNull($user->activeGroup());
    }

    public function test_make_admin_fails_if_capo_already_exists(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);

        $this->artisan('make:admin', [
            '--email' => 'admin2@example.com',
            '--password' => 'password',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin2@example.com']);
    }
}
