<?php

namespace Tests\Feature\Superadmin;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_renames_group(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $group = Group::create(['name' => 'Nome Vecchio', 'invite_code' => 'AAAA1111']);

        $response = $this->actingAs($superadmin)->put(route('superadmin.groups.update', $group), [
            'name' => 'Nome Nuovo',
        ]);

        $response->assertRedirect(route('superadmin.dashboard'));
        $this->assertSame('Nome Nuovo', $group->fresh()->name);
    }

    public function test_rename_requires_name(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $group = Group::create(['name' => 'Nome Vecchio', 'invite_code' => 'AAAA1111']);

        $response = $this->actingAs($superadmin)->put(route('superadmin.groups.update', $group), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertSame('Nome Vecchio', $group->fresh()->name);
    }

    public function test_regular_user_cannot_edit_group_from_superadmin_panel(): void
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Nome Vecchio', 'invite_code' => 'AAAA1111']);
        $group->addUser($user, Group::ROLE_CAPO);

        $this->actingAs($user)->get(route('superadmin.groups.edit', $group))->assertForbidden();
        $this->actingAs($user)->put(route('superadmin.groups.update', $group), ['name' => 'Nome Nuovo'])->assertForbidden();

        $this->assertSame('Nome Vecchio', $group->fresh()->name);
    }
}
