<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_after_5_attempts(): void
    {
        // 5 tentativi consentiti, il 6° deve essere bloccato (429)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);
            $response->assertStatus(302); // redirect back con errore
        }

        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);
        $response->assertStatus(429);
    }

    public function test_admin_mutations_are_rate_limited(): void
    {
        $user = User::factory()->withRole('capo')->create();

        // Supera il limite di 30 richieste/minuto
        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($user)->get(route('admin.vehicles.index'));
        }

        $response = $this->actingAs($user)->get(route('admin.vehicles.index'));
        $response->assertStatus(429);
    }

    public function test_rate_limiter_resets_after_window(): void
    {
        RateLimiter::clear('login');

        // Dopo il reset, il login funziona di nuovo
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);
        $response->assertStatus(302);
    }
}
