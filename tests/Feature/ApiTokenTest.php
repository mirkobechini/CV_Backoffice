<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_shows_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('App mobile');

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertSee('App mobile');
    }

    public function test_user_can_create_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/profile/tokens', [
            'token_name' => 'App mobile',
        ]);

        $response->assertRedirect('/profile');
        $response->assertSessionHas('plainTextToken');
        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'App mobile',
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_create_token_requires_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/profile/tokens', [
            'token_name' => '',
        ]);

        $response->assertSessionHasErrors('token_name');
    }

    public function test_user_can_revoke_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('App mobile');

        $response = $this->actingAs($user)->delete("/profile/tokens/{$token->accessToken->id}");

        $response->assertRedirect('/profile');
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_user_cannot_revoke_other_users_token(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherToken = $other->createToken('App mobile');

        $response = $this->actingAs($user)->delete("/profile/tokens/{$otherToken->accessToken->id}");

        $response->assertRedirect('/profile');
        // Il token dell'altro utente non deve essere eliminato
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);
    }
}
