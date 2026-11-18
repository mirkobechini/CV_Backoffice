<?php

namespace Tests\Unit;

use App\Models\VehicleCatalogLookup;
use App\Services\OpenVehicleCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenVehicleCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * L'endpoint /variants in elenco restituisce solo "engine_id", non i
     * dati del motore (verificato con una chiamata reale all'API): il
     * motore va mockato come seconda risposta su /engines/{id}.
     */
    private function fakeVariantResponse(?array $variant, ?array $engine = null): void
    {
        Http::fake([
            '*/api/v1/variants*' => Http::response([
                'total' => $variant ? 1 : 0,
                'items' => $variant ? [$variant] : [],
            ]),
            '*/api/v1/engines/*' => Http::response($engine ?? []),
        ]);
    }

    private function fiatDucatoVariant(array $overrides = []): array
    {
        return array_merge([
            'type_code' => '250',
            'variant_code' => 'AENRA',
            'version_code' => 'GE9M',
            'max_mass_kg' => 3150,
            'engine_id' => 'eng_diesel-2287-103',
        ], $overrides);
    }

    private function dieselEngine(array $overrides = []): array
    {
        return array_merge(['id' => 'eng_diesel-2287-103', 'fuel' => 'diesel', 'displacement_cc' => 2287, 'power_kw' => 103], $overrides);
    }

    public function test_lookup_returns_homologation_data_on_catalog_match(): void
    {
        $this->fakeVariantResponse($this->fiatDucatoVariant(), $this->dieselEngine());

        $result = app(OpenVehicleCatalogService::class)->lookup('250', 'AENRA', 'GE9M');

        $this->assertSame([
            'fuel_type' => 'diesel',
            'engine_displacement_cc' => 2287,
            'engine_power_kw' => 103,
            'max_mass_kg' => 3150,
        ], $result);
    }

    public function test_lookup_returns_null_when_catalog_has_no_match(): void
    {
        $this->fakeVariantResponse(null);

        $result = app(OpenVehicleCatalogService::class)->lookup('999', 'ZZZZZ', 'XX9');

        $this->assertNull($result);
    }

    public function test_lookup_returns_null_without_throwing_when_the_api_fails(): void
    {
        Http::fake(['*/api/v1/variants*' => Http::response(null, 500)]);

        $result = app(OpenVehicleCatalogService::class)->lookup('250', 'AENRA', 'GE9M');

        $this->assertNull($result);
    }

    public function test_lookup_still_returns_max_mass_kg_when_the_engine_call_fails(): void
    {
        // Il motore è una seconda chiamata separata: se fallisce,
        // fuel/cilindrata/potenza restano null ma max_mass_kg (già
        // nell'oggetto variante) resta comunque utilizzabile.
        Http::fake([
            '*/api/v1/variants*' => Http::response(['total' => 1, 'items' => [$this->fiatDucatoVariant()]]),
            '*/api/v1/engines/*' => Http::response(null, 500),
        ]);

        $result = app(OpenVehicleCatalogService::class)->lookup('250', 'AENRA', 'GE9M');

        $this->assertSame(3150, $result['max_mass_kg']);
        $this->assertNull($result['fuel_type']);
    }

    public function test_lookup_maps_catalog_fuel_values_to_the_apps_enum(): void
    {
        $this->fakeVariantResponse($this->fiatDucatoVariant(), $this->dieselEngine(['fuel' => 'petrol', 'displacement_cc' => 1368, 'power_kw' => 70]));

        $result = app(OpenVehicleCatalogService::class)->lookup('250', 'AENRA', 'GE9M');

        $this->assertSame('benzina', $result['fuel_type']);
    }

    public function test_lookup_leaves_fuel_type_null_for_a_fuel_not_covered_by_the_apps_enum(): void
    {
        $this->fakeVariantResponse($this->fiatDucatoVariant(), $this->dieselEngine(['fuel' => 'lpg', 'displacement_cc' => 1368, 'power_kw' => 70]));

        $result = app(OpenVehicleCatalogService::class)->lookup('250', 'AENRA', 'GE9M');

        $this->assertNull($result['fuel_type']);
    }

    public function test_lookup_caches_the_result_and_does_not_call_the_api_again(): void
    {
        $this->fakeVariantResponse($this->fiatDucatoVariant(), $this->dieselEngine());
        $service = app(OpenVehicleCatalogService::class);

        $service->lookup('250', 'AENRA', 'GE9M');
        Http::fake(['*/api/v1/variants*' => Http::response(null, 500)]);
        $result = $service->lookup('250', 'AENRA', 'GE9M');

        $this->assertNotNull($result);
        $this->assertSame('diesel', $result['fuel_type']);
    }

    public function test_lookup_caches_a_miss_too_and_does_not_retry_the_api(): void
    {
        $this->fakeVariantResponse(null);
        $service = app(OpenVehicleCatalogService::class);

        $service->lookup('999', 'ZZZZZ', 'XX9');
        Http::fake(['*/api/v1/variants*' => Http::response(null, 500)]);
        $result = $service->lookup('999', 'ZZZZZ', 'XX9');

        $this->assertNull($result);
        $this->assertDatabaseCount('vehicle_catalog_lookups', 1);
    }

    public function test_lookup_refetches_after_the_cache_expires(): void
    {
        VehicleCatalogLookup::create([
            'type_code' => '250',
            'variant_code' => 'AENRA',
            'version_code' => 'GE9M',
            'found' => true,
            'fuel_type' => 'diesel',
            'engine_displacement_cc' => 2287,
            'engine_power_kw' => 103,
            'max_mass_kg' => 3150,
            'looked_up_at' => now()->subDays(31),
        ]);
        $this->fakeVariantResponse($this->fiatDucatoVariant(['max_mass_kg' => 3200]), $this->dieselEngine());

        $result = app(OpenVehicleCatalogService::class)->lookup('250', 'AENRA', 'GE9M');

        $this->assertSame(3200, $result['max_mass_kg']);
    }
}
