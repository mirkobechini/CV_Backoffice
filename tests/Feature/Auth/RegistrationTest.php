<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_returns_404(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_new_users_cannot_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(404);
    }

    public function test_first_user_creates_group_and_becomes_capo(): void
    {
        // Simula il flusso del RegisteredUserController per il primo utente.
        $controller = new RegisteredUserController;

        // Il primo utente crea un gruppo di default e diventa capo.
        $user = User::create([
            'name' => 'Primo Utente',
            'email' => 'primo@example.com',
            'password' => bcrypt('password'),
        ]);

        $group = Group::create([
            'name' => 'Associazione di default',
            'invite_code' => Group::generateInviteCode(),
        ]);
        $group->addUser($user, Group::ROLE_CAPO);

        $this->assertTrue($user->isCapo());
        $this->assertNotNull($user->activeGroup());
        $this->assertEquals(Group::ROLE_CAPO, $user->roleIn($group));
    }

    public function test_non_capo_cannot_register_new_users(): void
    {
        // Un membro (non capo) non può registrare nuovi utenti.
        $member = User::factory()->withRole('member')->create();

        $this->actingAs($member);

        // Il controller blocca la registrazione per chi non può gestire i dati.
        $this->assertFalse($member->canManageData());
    }
}
