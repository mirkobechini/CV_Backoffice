<?php

namespace Tests\Feature;

use App\Models\EquipmentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashStatusMessageTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    public function test_layout_shows_the_success_message_flashed_by_admin_controllers(): void
    {
        // Audit 2026-10-22 (B10): layouts/app.blade.php non renderizzava
        // affatto session('status'), restando invisibile sulla
        // stragrande maggioranza delle pagine admin.
        $user = $this->createUser();

        $response = $this->actingAs($user)->post(route('admin.equipment-types.store'), [
            'name' => 'Estintore',
            'category' => EquipmentType::CATEGORY_FIRE_EXTINGUISHER,
        ]);

        $response->assertRedirect();
        $redirected = $this->followRedirects($response);
        $redirected->assertSee('Tipo di attrezzatura creato con successo.');
    }

    public function test_layout_does_not_leak_raw_internal_status_codes(): void
    {
        // profile-updated/token-created/ecc. sono codici brevi interni
        // tradotti in un messaggio dedicato dalla vista profilo stessa:
        // il banner generico non deve stamparli grezzi.
        $user = $this->createUser();

        $response = $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $response->assertRedirect();
        $redirected = $this->followRedirects($response);
        $redirected->assertDontSee('profile-updated');
        $redirected->assertSee('Salvato.');
    }
}
