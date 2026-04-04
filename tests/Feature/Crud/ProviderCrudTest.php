<?php

namespace Tests\Feature\Crud;

use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderCrudTest extends TestCase
{

    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->create();
    }

    private function createProvider(): Provider
    {
        return Provider::create([
            'name' => "boh",
            'contact_info' => "+39 3885245",
            'address' => 'Via roma 2, Milano',
            'type' => 'Meccanico',
        ]);
    }

    public function test_provider_index_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.providers.index'));

        $response->assertStatus(200);
    }


    public function test_provider_create_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user

        $response = $this->actingAs($user)->get(route('admin.providers.create'));

        $response->assertStatus(200);
    }



    public function test_provider_show_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $provider = $this->createProvider();

        $response = $this->actingAs($user)->get(route('admin.providers.show', $provider));

        $response->assertStatus(200);
    }

    public function test_provider_edit_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $provider = $this->createProvider();

        $response = $this->actingAs($user)->get(route('admin.providers.edit', $provider));

        $response->assertStatus(200);
    }


    public function test_provider_can_be_stored(): void
    {
        $user = $this->createUser();    //fake user

        $response = $this->actingAs($user)->post(route('admin.providers.store'), [
            'name' => "boh",
            'contact_info' => "+39 3885245",
            'address' => 'Via roma 2, Milano',
            'type' => 'Meccanico',
        ]);

        $provider = Provider::first();

        $response->assertRedirect(route('admin.providers.show', $provider));
        $this->assertDatabaseHas('providers', [
            'id' => $provider->id,
            'name' => 'boh',
            'contact_info' => '+39 3885245',
            'address' => 'Via roma 2, Milano',
            'type' => 'Meccanico',
        ]);
    }


    public function test_provider_can_be_updated(): void
    {
        $user = $this->createUser();
        $provider = $this->createProvider();

        $response = $this->actingAs($user)->put(route('admin.providers.update', $provider), [
            'name' => "mah",
            'contact_info' => "+39 3885245",
            'address' => 'Via milano 2, Napoli',
            'type' => 'Carrozziere',
        ]);

        $response->assertRedirect(route('admin.providers.show', $provider));
        $this->assertDatabaseHas('providers', [
            'id' => $provider->id,
            'name' => 'mah',
            'contact_info' => '+39 3885245',
            'address' => 'Via milano 2, Napoli',
            'type' => 'Carrozziere',
        ]);
    }

    public function test_provider_can_be_deleted(): void
    {
        $user = $this->createUser();

        $provider = $this->createProvider();

        $response = $this->actingAs($user)->delete(route('admin.providers.destroy', $provider));

        $response->assertRedirect(route('admin.providers.index'));
        $this->assertDatabaseMissing('providers', [
            'id' => $provider->id,
        ]);
    }
}
