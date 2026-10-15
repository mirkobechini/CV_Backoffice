<?php

namespace Tests\Feature\Superadmin;

use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
        ]);

        $group = Group::where('name', 'Croce Verde Esempio')->first();
        $this->assertNotNull($group);

        $response->assertRedirect(route('superadmin.licenses.create', $group));

        $capo = User::where('email', 'luca@example.com')->first();
        $this->assertNotNull($capo);
        $this->assertSame(Group::ROLE_CAPO, $capo->roleIn($group));
    }

    public function test_new_capo_receives_a_set_password_link_the_superadmin_never_sees(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $this->actingAs($superadmin)->post(route('superadmin.groups.store'), [
            'name' => 'Croce Verde Esempio',
            'capo_name' => 'Luca Verdi',
            'capo_email' => 'luca@example.com',
        ]);

        // Password::sendResetLink() crea un token prima di inviare la
        // notifica: la sua presenza prova che il flusso di impostazione
        // password è partito, senza che il superadmin sia mai passato da
        // una password in chiaro (non gliene è stata chiesta nessuna).
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'luca@example.com']);
    }

    public function test_new_capo_also_receives_a_verification_email_immediately(): void
    {
        Notification::fake();
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $this->actingAs($superadmin)->post(route('superadmin.groups.store'), [
            'name' => 'Croce Verde Esempio',
            'capo_name' => 'Luca Verdi',
            'capo_email' => 'luca@example.com',
        ]);

        $capo = User::where('email', 'luca@example.com')->first();
        Notification::assertSentTo($capo, VerifyEmail::class);
    }

    public function test_rejects_duplicate_capo_email(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        User::factory()->create(['email' => 'esiste@example.com']);

        $response = $this->actingAs($superadmin)->post(route('superadmin.groups.store'), [
            'name' => 'Croce Verde Esempio',
            'capo_name' => 'Luca Verdi',
            'capo_email' => 'esiste@example.com',
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
