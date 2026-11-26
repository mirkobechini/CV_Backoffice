<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class UserCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_capo_creates_a_user_and_both_notifications_are_sent(): void
    {
        Notification::fake();
        $capo = User::factory()->withRole('capo')->create();
        $group = $capo->activeGroup();

        $response = $this->actingAs($capo)->post(route('admin.groups.users.store', $group), [
            'name' => 'Nuovo Utente',
            'email' => 'nuovo@example.com',
            'role' => 'member',
        ]);

        $response->assertRedirect(route('admin.groups.show', $group));
        $newUser = User::where('email', 'nuovo@example.com')->first();
        $this->assertNotNull($newUser);
        Notification::assertSentTo($newUser, ResetPassword::class);
        Notification::assertSentTo($newUser, VerifyEmail::class);
    }

    public function test_user_is_still_created_when_sending_the_password_link_fails(): void
    {
        // Prima di questo fix, un fallimento nell'invio (SMTP/Resend
        // irraggiungibile) faceva 500 l'intera richiesta: l'utente
        // risultava comunque creato in DB, ma senza che l'admin lo sapesse
        // né alcun modo per il nuovo utente di impostare la password.
        $capo = User::factory()->withRole('capo')->create();
        $group = $capo->activeGroup();

        Password::shouldReceive('sendResetLink')->once()->andThrow(new \Exception('SMTP irraggiungibile'));

        $response = $this->actingAs($capo)->post(route('admin.groups.users.store', $group), [
            'name' => 'Nuovo Utente',
            'email' => 'nuovo@example.com',
            'role' => 'member',
        ]);

        $response->assertRedirect(route('admin.groups.show', $group));
        $response->assertSessionHas('status');
        $this->assertStringContainsString('fallito', session('status'));
        $this->assertDatabaseHas('users', ['email' => 'nuovo@example.com']);
    }
}
