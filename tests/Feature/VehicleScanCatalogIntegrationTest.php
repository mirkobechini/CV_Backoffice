<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * F3: il catalogo openvehicle-data corregge i dati di omologazione letti
 * dall'AI (vedi VehicleScanController::scanRegistrationCard()). Test
 * separati da VehicleScanTest (già oltre le ~500 righe convenzionali).
 */
class VehicleScanCatalogIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.key' => 'test-key']);
    }

    private function capo(): User
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => Group::generateInviteCode()]);
        $group->addUser($user, Group::ROLE_CAPO);

        return $user;
    }

    private function vehicleDeps(): void
    {
        $brand = Brand::create(['name' => 'Fiat']);
        CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        VehicleType::create(['name' => 'Ambulanza', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
    }

    private function extractedFieldsStub(array $overrides = []): array
    {
        return array_merge([
            'license_plate' => 'AB123CD',
            'immatricolation_date' => '2024-01-01',
            'fuel_type' => 'benzina',
            'brand' => 'Fiat',
            'car_model' => 'Ducato',
            'vin' => 'ZFA1234567890',
            'color' => 'Bianco',
            'seats' => 5,
            'environmental_class' => 'Euro 6',
            'max_mass_kg' => 3000,
            'engine_displacement_cc' => 2000,
            'engine_power_kw' => 90,
            'vehicle_category' => 'M1',
            'allowed_tire_sizes' => [],
            'has_timing_belt_suggested' => null,
            'type_code' => '250',
            'variant_code' => 'AENRA',
            'version_code' => 'GE9M',
        ], $overrides);
    }

    private function fakeChatCompletion(array $fields): void
    {
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode($fields)]],
                ],
            ]),
            '*/api/v1/variants*' => Http::response(['total' => 0, 'items' => []]),
        ]);
    }

    /**
     * L'endpoint /variants in elenco restituisce solo "engine_id", non i
     * dati del motore (verificato con una chiamata reale all'API): $engine
     * mocka la seconda chiamata su /engines/{id}.
     */
    private function fakeCatalogMatch(array $variant, array $engine): void
    {
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode($this->extractedFieldsStub())]],
                ],
            ]),
            '*/api/v1/variants*' => Http::response(['total' => 1, 'items' => [[...$variant, 'engine_id' => 'eng_test']]]),
            '*/api/v1/engines/*' => Http::response($engine),
        ]);
    }

    public function test_catalog_match_overrides_the_ai_guessed_fuel_type(): void
    {
        Storage::fake('public');
        $user = $this->capo();
        $this->vehicleDeps();

        // L'AI indovina "benzina" (extractedFieldsStub di default), il
        // catalogo dice "diesel": il catalogo vince.
        $this->fakeCatalogMatch(
            ['max_mass_kg' => 3150],
            ['fuel' => 'diesel', 'displacement_cc' => 2287, 'power_kw' => 103],
        );

        $response = $this->actingAs($user)->post(route('admin.vehicles.scan-libretto'), [
            'photo_front' => UploadedFile::fake()->create('fronte.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertSessionHasInput('fuel_type', 'diesel');
        $response->assertSessionHasInput('engine_displacement_cc', 2287);
        $response->assertSessionHasInput('engine_power_kw', 103);
        $response->assertSessionHasInput('max_mass_kg', 3150);
        $response->assertSessionHas('catalog_matched', true);

        $createPage = $this->actingAs($user)->followingRedirects()->get(route('admin.vehicles.create'));
        $createPage->assertSee('Dal catalogo');
    }

    public function test_catalog_field_missing_from_the_match_keeps_the_ai_guess(): void
    {
        Storage::fake('public');
        $user = $this->capo();
        $this->vehicleDeps();

        // Il catalogo copre carburante/cilindrata/potenza ma non la massa
        // massima (comune, ~48% di copertura reale): quel campo resta
        // quello letto dall'AI invece di sparire.
        $this->fakeCatalogMatch(
            ['max_mass_kg' => null],
            ['fuel' => 'diesel', 'displacement_cc' => 2287, 'power_kw' => 103],
        );

        $response = $this->actingAs($user)->post(route('admin.vehicles.scan-libretto'), [
            'photo_front' => UploadedFile::fake()->create('fronte.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertSessionHasInput('fuel_type', 'diesel');
        $response->assertSessionHasInput('max_mass_kg', 3000);
    }

    public function test_no_catalog_match_falls_back_to_the_ai_guess(): void
    {
        Storage::fake('public');
        $user = $this->capo();
        $this->vehicleDeps();

        $this->fakeChatCompletion($this->extractedFieldsStub());

        $response = $this->actingAs($user)->post(route('admin.vehicles.scan-libretto'), [
            'photo_front' => UploadedFile::fake()->create('fronte.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertSessionHasInput('fuel_type', 'benzina');
        $response->assertSessionMissing('catalog_matched');
    }

    public function test_catalog_api_failure_falls_back_to_the_ai_guess_without_erroring(): void
    {
        Storage::fake('public');
        $user = $this->capo();
        $this->vehicleDeps();

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode($this->extractedFieldsStub())]],
                ],
            ]),
            '*/api/v1/variants*' => Http::response(null, 500),
        ]);

        $response = $this->actingAs($user)->post(route('admin.vehicles.scan-libretto'), [
            'photo_front' => UploadedFile::fake()->create('fronte.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertSessionHasInput('fuel_type', 'benzina');
        $response->assertSessionMissing('catalog_matched');
    }

    public function test_missing_homologation_code_skips_the_catalog_lookup(): void
    {
        Storage::fake('public');
        $user = $this->capo();
        $this->vehicleDeps();

        $this->fakeChatCompletion($this->extractedFieldsStub(['type_code' => null, 'variant_code' => null, 'version_code' => null]));

        $response = $this->actingAs($user)->post(route('admin.vehicles.scan-libretto'), [
            'photo_front' => UploadedFile::fake()->create('fronte.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertSessionHasInput('fuel_type', 'benzina');
        $response->assertSessionMissing('catalog_matched');
        $this->assertDatabaseCount('vehicle_catalog_lookups', 0);
    }
}
