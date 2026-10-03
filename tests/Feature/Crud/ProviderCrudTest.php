<?php

namespace Tests\Feature\Crud;

use App\Models\Group;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderCrudTest extends TestCase
{

    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    /**
     * Stesso gruppo creato dallo stato "admin" di UserFactory::withRole():
     * i veicoli devono appartenervi per essere visibili/accessibili
     * all'utente di questi test (le route sono scoperte per gruppo).
     */
    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
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

    public function test_provider_can_be_stored_with_vetri_type(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->post(route('admin.providers.store'), [
            'name' => 'Vetreria Test',
            'contact_info' => '+39 3885245',
            'address' => 'Via roma 2, Milano',
            'type' => 'Vetri',
        ]);

        $provider = Provider::first();

        $response->assertRedirect(route('admin.providers.show', $provider));
        $this->assertDatabaseHas('providers', [
            'id' => $provider->id,
            'name' => 'Vetreria Test',
            'type' => 'Vetri',
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
        $this->assertSoftDeleted('providers', [
            'id' => $provider->id,
        ]);
    }

    // VALIDAZIONE DEI CAMPI OBBLIGATORI

    public function test_provider_name_is_required(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->post(route('admin.providers.store'), [
            'contact_info' => '+39 3885245',
            'address' => 'Via roma 2, Milano',
            'type' => 'Meccanico',
        ]);

        // Verifica che il campo `name` sia obbligatorio.
        $response->assertSessionHasErrors(['name']);
        $this->assertDatabaseCount('providers', 0); // Conferma che non venga creato alcun provider senza nome.
    }

    public function test_usage_filter_distinguishes_vehicle_and_equipment_providers(): void
    {
        // Non una categoria fissa: lo stesso fornitore può servire sia
        // mezzi che attrezzature, il filtro verifica l'uso reale.
        $user = $this->createUser();

        $vehicleOnlyProvider = Provider::create(['name' => 'Solo veicoli', 'type' => 'Meccanico']);
        $equipmentOnlyProvider = Provider::create(['name' => 'Solo attrezzature', 'type' => 'Meccanico']);
        $bothProvider = Provider::create(['name' => 'Entrambi', 'type' => 'Meccanico']);
        $unusedProvider = Provider::create(['name' => 'Non usato', 'type' => 'Meccanico']);

        $brand = \App\Models\Brand::create(['name' => 'Fiat']);
        $model = \App\Models\CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $vehicleType = \App\Models\VehicleType::create(['name' => 'Amb', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $vehicle = \App\Models\Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $vehicleType->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ]);
        \App\Models\MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $vehicleOnlyProvider->id,
            'appointment_date' => today(),
        ]);
        \App\Models\MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $bothProvider->id,
            'appointment_date' => today(),
        ]);

        $equipmentType = \App\Models\EquipmentType::create(['name' => 'Estintore']);
        $equipment = \App\Models\Equipment::create([
            'equipment_type_id' => $equipmentType->id,
            'name' => 'Estintore test',
            'serial_number' => 'SN-1',
        ]);
        \App\Models\EquipmentMaintenanceRecord::create([
            'provider_id' => $equipmentOnlyProvider->id,
            'appointment_date' => today(),
        ]);
        \App\Models\EquipmentMaintenanceRecord::create([
            'provider_id' => $bothProvider->id,
            'appointment_date' => today(),
        ]);

        $vehiclesResponse = $this->actingAs($user)->get(route('admin.providers.index', ['usage_filter' => 'vehicles']));
        $vehiclesResponse->assertSee('Solo veicoli')->assertSee('Entrambi');
        $vehiclesResponse->assertDontSee('Solo attrezzature')->assertDontSee('Non usato');

        $equipmentResponse = $this->actingAs($user)->get(route('admin.providers.index', ['usage_filter' => 'equipment']));
        $equipmentResponse->assertSee('Solo attrezzature')->assertSee('Entrambi');
        $equipmentResponse->assertDontSee('Solo veicoli')->assertDontSee('Non usato');

        $allResponse = $this->actingAs($user)->get(route('admin.providers.index'));
        $allResponse->assertSee('Non usato');
    }
}
