<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_page_is_accessible(): void
    {
        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertSee('Informativa Privacy');
    }

    public function test_privacy_page_accessible_when_authenticated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/privacy');

        $response->assertOk();
        $response->assertSee('Informativa Privacy');
    }

    public function test_privacy_page_credits_the_openvehicle_data_license(): void
    {
        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertSee('openvehicle-data');
        $response->assertSee('CC BY 4.0');
    }

    public function test_privacy_page_lists_the_external_data_processors(): void
    {
        $response = $this->get('/privacy');

        $response->assertOk();
        $response->assertSee('Resend');
        $response->assertSee('Cloudflare R2');
        $response->assertSee('OpenRouter');
        $response->assertSee('Telegram');
        $response->assertSee(config('mail.from.address'));
    }

    public function test_cookie_banner_is_present_in_app_layout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('cookie-banner');
        $response->assertSee('Utilizziamo i cookie');
    }
}
