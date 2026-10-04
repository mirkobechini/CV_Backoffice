<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function enabledUser(): array
    {
        $secret = (new Google2FA())->generateSecretKey();
        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);
        $user->generateRecoveryCodes();

        return [$user, $secret];
    }

    private function currentCode(string $secret): string
    {
        return (new Google2FA())->getCurrentOtp($secret);
    }

    public function test_login_with_two_factor_enabled_requires_challenge_instead_of_logging_in(): void
    {
        [$user] = $this->enabledUser();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('two-factor.challenge'));
    }

    public function test_login_without_two_factor_still_logs_in_directly(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_challenge_with_correct_code_completes_login(): void
    {
        [$user, $secret] = $this->enabledUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertGuest();

        $response = $this->post(route('two-factor.challenge'), [
            'code' => $this->currentCode($secret),
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_challenge_with_wrong_code_does_not_log_in(): void
    {
        [$user] = $this->enabledUser();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response = $this->post(route('two-factor.challenge'), [
            'code' => '000000',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('code');
    }

    public function test_challenge_with_valid_recovery_code_completes_login_and_consumes_it(): void
    {
        [$user] = $this->enabledUser();
        $code = $user->two_factor_recovery_codes[0];

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response = $this->post(route('two-factor.challenge'), [
            'recovery_code' => $code,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertNotContains($code, $user->fresh()->two_factor_recovery_codes);
    }

    public function test_recovery_code_cannot_be_reused(): void
    {
        [$user] = $this->enabledUser();
        $code = $user->two_factor_recovery_codes[0];
        $user->redeemRecoveryCode($code);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response = $this->post(route('two-factor.challenge'), [
            'recovery_code' => $code,
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('code');
    }

    public function test_user_can_enable_and_confirm_two_factor_from_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('two-factor.store'));
        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());

        $secret = $user->fresh()->two_factor_secret;
        $response = $this->actingAs($user)->post(route('two-factor.confirm'), [
            'code' => $this->currentCode($secret),
        ]);

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
        $response->assertSessionHas('recoveryCodes');
    }

    public function test_confirm_with_wrong_code_does_not_enable_two_factor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('two-factor.store'));

        $this->actingAs($user)->post(route('two-factor.confirm'), [
            'code' => '000000',
        ]);

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_user_can_disable_two_factor_with_correct_password(): void
    {
        [$user] = $this->enabledUser();

        $this->actingAs($user)->delete(route('two-factor.destroy'), [
            'password' => 'password',
        ]);

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_disabling_two_factor_requires_correct_password(): void
    {
        [$user] = $this->enabledUser();

        $response = $this->actingAs($user)->delete(route('two-factor.destroy'), [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrorsIn('twoFactor', 'password');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }
}
