<?php

namespace Tests\Feature\Superadmin;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_creates_group_with_first_capo(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $response = $this->actingAs($superadmin)->post(route('superadmin.groups.store'), [
            'name' => 'Croce Verde Esempio',
            'capo_name' => 'Luca Verdi',
            'capo_email' => 'luca@example.com',
            'capo_password' => 'password123',
            'capo_password_confirmation' => 'password123',
        ]);

        $group = Group::where('name', 'Croce Verde Esempio')->first();
        $this->assertNotNull($group);

        $response->assertRedirect(route('superadmin.licenses.create', $group));

        $capo = User::where('email', 'luca@example.com')->first();
        $this->assertNotNull($capo);
        $this->assertSame(Group::ROLE_CAPO, $capo->roleIn($group));
    }

    public function test_rejects_duplicate_capo_email(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        User::factory()->create(['email' => 'esiste@example.com']);

        $response = $this->actingAs($superadmin)->post(route('superadmin.groups.store'), [
            'name' => 'Croce Verde Esempio',
            'capo_name' => 'Luca Verdi',
            'capo_email' => 'esiste@example.com',
            'capo_password' => 'password123',
            'capo_password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('capo_email');
        $this->assertDatabaseMissing('groups', ['name' => 'Croce Verde Esempio']);
    }

    public function test_regular_user_cannot_create_group_from_superadmin_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('superadmin.groups.create'))->assertForbidden();
        $this->actingAs($user)->post(route('superadmin.groups.store'), [
            'name' => 'Croce Verde Esempio',
            'capo_name' => 'Luca Verdi',
            'capo_email' => 'luca@example.com',
            'capo_password' => 'password123',
            'capo_password_confirmation' => 'password123',
        ])->assertForbidden();

        $this->assertDatabaseMissing('groups', ['name' => 'Croce Verde Esempio']);
    }

    public function test_superadmin_creates_group_with_self_as_capo(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $response = $this->actingAs($superadmin)->post(route('superadmin.groups.store'), [
            'name' => 'Il Mio Gruppo',
            'self_capo' => '1',
        ]);

        $group = Group::where('name', 'Il Mio Gruppo')->first();
        $this->assertNotNull($group);
        $response->assertRedirect(route('superadmin.licenses.create', $group));

        $this->assertSame(Group::ROLE_CAPO, $superadmin->roleIn($group));
        $this->assertSame(1, $group->users()->count());
    }
}
