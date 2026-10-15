<?php

namespace Tests\Feature\Superadmin;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapoCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_corrects_capo_email(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $capo = User::factory()->create(['email' => 'typo@exmple.com', 'email_verified_at' => now()]);
        $group->addUser($capo, Group::ROLE_CAPO);

        $response = $this->actingAs($superadmin)->put(route('superadmin.groups.capo.update', $group), [
            'capo_name' => $capo->name,
            'capo_email' => 'corretta@example.com',
        ]);

        $response->assertRedirect(route('superadmin.groups.edit', $group));
        $capo->refresh();
        $this->assertSame('corretta@example.com', $capo->email);
        $this->assertNull($capo->email_verified_at);
    }

    public function test_keeping_the_same_email_does_not_reset_verification(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $capo = User::factory()->create(['email' => 'capo@example.com', 'email_verified_at' => now()]);
        $group->addUser($capo, Group::ROLE_CAPO);

        $this->actingAs($superadmin)->put(route('superadmin.groups.capo.update', $group), [
            'capo_name' => 'Nome Aggiornato',
            'capo_email' => 'capo@example.com',
        ]);

        $capo->refresh();
        $this->assertSame('Nome Aggiornato', $capo->name);
        $this->assertNotNull($capo->email_verified_at);
    }

    public function test_rejects_email_already_used_by_another_user(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $capo = User::factory()->create(['email' => 'capo@example.com']);
        $group->addUser($capo, Group::ROLE_CAPO);
        User::factory()->create(['email' => 'occupata@example.com']);

        $response = $this->actingAs($superadmin)->put(route('superadmin.groups.capo.update', $group), [
            'capo_name' => $capo->name,
            'capo_email' => 'occupata@example.com',
        ]);

        $response->assertSessionHasErrors('capo_email');
        $this->assertSame('capo@example.com', $capo->fresh()->email);
    }

    public function test_resend_password_link_creates_a_new_token(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $capo = User::factory()->create(['email' => 'capo@example.com']);
        $group->addUser($capo, Group::ROLE_CAPO);

        $response = $this->actingAs($superadmin)->post(route('superadmin.groups.capo.resend-password-link', $group));

        $response->assertRedirect(route('superadmin.groups.edit', $group));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'capo@example.com']);
    }

    public function test_regular_user_cannot_correct_capo_email(): void
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $capo = User::factory()->create(['email' => 'capo@example.com']);
        $group->addUser($capo, Group::ROLE_CAPO);

        $response = $this->actingAs($user)->put(route('superadmin.groups.capo.update', $group), [
            'capo_name' => $capo->name,
            'capo_email' => 'altra@example.com',
        ]);

        $response->assertForbidden();
        $this->assertSame('capo@example.com', $capo->fresh()->email);
    }
}
