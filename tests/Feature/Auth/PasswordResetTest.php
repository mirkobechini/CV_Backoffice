<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_link_request_fails_gracefully_when_sending_throws(): void
    {
        // Prima di questo fix, un fallimento nell'invio (SMTP/Resend
        // irraggiungibile) risultava in un 500 non gestito invece di un
        // normale errore di validazione mostrato all'utente.
        $user = User::factory()->create();

        Password::shouldReceive('sendResetLink')->once()->andThrow(new \Exception('SMTP irraggiungibile'));

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_password_reset_revokes_existing_api_tokens(): void
    {
        // Audit sicurezza SL4: un token API rubato (es. da un dispositivo
        // perso) restava valido anche dopo il reset password, lo stesso
        // motivo per cui l'utente lo sta facendo.
        Notification::fake();

        $user = User::factory()->create();
        $user->createToken('mobile-app');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

            return true;
        });

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
