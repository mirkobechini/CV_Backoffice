<?php

namespace Tests\Feature\Superadmin;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
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

    public function test_resend_capo_password_link_succeeds(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $group = Group::create(['name' => 'Croce Verde', 'invite_code' => 'AAAA1111']);
        $capo = User::factory()->create();
        $group->addUser($capo, Group::ROLE_CAPO);

        $response = $this->actingAs($superadmin)->post(route('superadmin.groups.capo.resend-password-link', $group));

        $response->assertRedirect(route('superadmin.groups.edit', $group));
        $this->assertStringContainsString('inviata', session('status'));
    }

    public function test_resend_capo_password_link_reports_failure_instead_of_500(): void
    {
        // Prima di questo fix, un fallimento nell'invio (SMTP/Resend
        // irraggiungibile) faceva 500 invece di tornare alla pagina con
        // un messaggio chiaro.
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $group = Group::create(['name' => 'Croce Verde', 'invite_code' => 'BBBB2222']);
        $capo = User::factory()->create();
        $group->addUser($capo, Group::ROLE_CAPO);

        Password::shouldReceive('sendResetLink')->once()->andThrow(new \Exception('SMTP irraggiungibile'));

        $response = $this->actingAs($superadmin)->post(route('superadmin.groups.capo.resend-password-link', $group));

        $response->assertRedirect(route('superadmin.groups.edit', $group));
        $this->assertStringContainsString('fallito', session('status'));
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
