<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_and_user(): void
    {
        $user = User::factory()->withRole('capo')->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
            'device_name' => 'test-device',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']])
            ->assertJsonPath('user.email', 'test@example.com')
            ->assertJsonPath('user.role', 'capo');
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_fails_when_email_is_not_verified(): void
    {
        // Audit sicurezza 2026-10-22 (SH2): il login API prima bypassava
        // la verifica email imposta dal login web.
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_login_requires_two_factor_code_when_enabled(): void
    {
        // Audit sicurezza 2026-10-22 (SH2): il login API prima bypassava
        // il 2FA imposto dal login web.
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'two_factor_secret' => 'ABCDEFGHIJKLMNOP',
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('code');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertNotNull($user);
    }

    public function test_login_succeeds_with_valid_two_factor_code(): void
    {
        $secret = 'ABCDEFGHIJKLMNOP';
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $code = (new Google2FA)->getCurrentOtp($secret);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
            'code' => $code,
        ]);

        $response->assertOk()->assertJsonPath('user.email', $user->email);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->withRole('member')->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/logout');

        $response->assertOk()
            ->assertJsonPath('message', 'Logout effettuato.');

        // Il token dovrebbe essere revocato
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
