<?php

namespace Tests\Feature\Superadmin;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperadminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_cannot_access_superadmin_dashboard(): void
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($user, Group::ROLE_CAPO);

        $response = $this->actingAs($user)->get(route('superadmin.dashboard'));

        $response->assertForbidden();
    }

    public function test_superadmin_can_access_superadmin_dashboard(): void
    {
        $user = User::factory()->create(['is_superadmin' => true]);

        $response = $this->actingAs($user)->get(route('superadmin.dashboard'));

        $response->assertOk();
    }

    public function test_superadmin_who_is_also_capo_keeps_access_to_admin_panel(): void
    {
        $user = User::factory()->create(['is_superadmin' => true]);
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($user, Group::ROLE_CAPO);

        $this->actingAs($user)->get(route('superadmin.dashboard'))->assertOk();
        $this->actingAs($user)->get(route('admin.vehicles.index'))->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('superadmin.dashboard'));

        $response->assertRedirect(route('login'));
    }
}
