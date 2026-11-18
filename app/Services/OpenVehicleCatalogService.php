<?php

namespace App\Services;

use App\Exceptions\OpenVehicleCatalogException;
use App\Models\VehicleCatalogLookup;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Dati di omologazione (carburante, cilindrata, potenza, massa massima)
 * dal catalogo esterno openvehicle-data (github.com/mirkobechini/openvehicle-data,
 * CC BY 4.0 — vedi attribuzione in resources/views/legal/attribuzioni.blade.php),
 * cercati per codice di omologazione (campo D.2 del libretto: tipo,
 * variante, versione). Usato da VehicleScanController per correggere i
 * dati di omologazione letti dall'AI (spesso inaffidabili, es. carburante
 * sempre "benzina") — l'AI resta fallback se qui non si trova un
 * riscontro, mai bloccante (stesso principio di VehicleScanService).
 */
class OpenVehicleCatalogService
{
    /**
     * Il dato di omologazione di una variante già registrata non cambia
     * mai: una cache lunga evita di richiamare l'API per lo stesso
     * modello comprato più volte dalla stessa associazione, senza
     * rischiare di servire un dato stantio se il catalogo viene corretto.
     */
    private const CACHE_TTL_DAYS = 30;

    /**
     * Il catalogo distingue più alimentazioni di quante l'app ne preveda
     * oggi (Vehicle::fuelTypeLabels()): lpg/cng/hydrogen/other restano
     * senza corrispondenza, il campo resta null e l'AI fa da fallback.
     */
    private const FUEL_MAP = [
        'petrol' => 'benzina',
        'diesel' => 'diesel',
        'electric' => 'elettrico',
        'hybrid' => 'ibrido',
        'phev' => 'ibrido',
    ];

    public function __construct(
        private ?string $baseUrl = null,
        private ?int $timeout = null,
    ) {
        $this->baseUrl ??= config('services.openvehicle.base_url');
        $this->timeout ??= config('services.openvehicle.timeout');
    }

    /**
     * @return array{fuel_type: ?string, engine_displacement_cc: ?int, engine_power_kw: ?int, max_mass_kg: ?int}|null
     *                                                                                                                null se non trovato riscontro nel catalogo: nessuna eccezione propagata, il chiamante ricade sui dati AI.
     */
    public function lookup(string $typeCode, string $variantCode, string $versionCode): ?array
    {
        $cached = VehicleCatalogLookup::query()
            ->where('type_code', $typeCode)
            ->where('variant_code', $variantCode)
            ->where('version_code', $versionCode)
            ->where('looked_up_at', '>=', now()->subDays(self::CACHE_TTL_DAYS))
            ->first();

        if ($cached) {
            return $cached->found ? $this->fromCache($cached) : null;
        }

        try {
            $variant = $this->fetchFromApi($typeCode, $variantCode, $versionCode);
        } catch (OpenVehicleCatalogException $e) {
            Log::warning("Lookup catalogo openvehicle-data fallita: {$e->getMessage()}", [
                'type_code' => $typeCode,
                'variant_code' => $variantCode,
                'version_code' => $versionCode,
            ]);

            return null;
        }

        $data = $variant ? $this->mapVariant($variant) : null;

        VehicleCatalogLookup::updateOrCreate(
            ['type_code' => $typeCode, 'variant_code' => $variantCode, 'version_code' => $versionCode],
            [
                'found' => $variant !== null,
                'fuel_type' => $data['fuel_type'] ?? null,
                'engine_displacement_cc' => $data['engine_displacement_cc'] ?? null,
                'engine_power_kw' => $data['engine_power_kw'] ?? null,
                'max_mass_kg' => $data['max_mass_kg'] ?? null,
                'looked_up_at' => now(),
            ]
        );

        return $data;
    }

    /**
     * @return array<string, mixed>|null la prima variante trovata, o null se nessuna corrisponde ai tre codici.
     */
    private function fetchFromApi(string $typeCode, string $variantCode, string $versionCode): ?array
    {
        $response = Http::timeout($this->timeout)
            ->get(rtrim($this->baseUrl, '/').'/api/v1/variants', [
                'type_code' => $typeCode,
                'variant_code' => $variantCode,
                'version_code' => $versionCode,
            ]);

        if (! $response->successful()) {
            throw new OpenVehicleCatalogException('Chiamata al catalogo fallita: '.$response->status());
        }

        $items = $response->json('items');

        if (! is_array($items)) {
            throw new OpenVehicleCatalogException('Risposta del catalogo non valida.');
        }

        return $items[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $variant
     * @return array{fuel_type: ?string, engine_displacement_cc: ?int, engine_power_kw: ?int, max_mass_kg: ?int}
     */
    private function mapVariant(array $variant): array
    {
        $fuel = $variant['engine']['fuel'] ?? null;

        return [
            'fuel_type' => self::FUEL_MAP[$fuel] ?? null,
            'engine_displacement_cc' => $variant['engine']['displacement_cc'] ?? null,
            'engine_power_kw' => $variant['engine']['power_kw'] ?? null,
            'max_mass_kg' => $variant['max_mass_kg'] ?? null,
        ];
    }

    /**
     * @return array{fuel_type: ?string, engine_displacement_cc: ?int, engine_power_kw: ?int, max_mass_kg: ?int}
     */
    private function fromCache(VehicleCatalogLookup $lookup): array
    {
        return [
            'fuel_type' => $lookup->fuel_type,
            'engine_displacement_cc' => $lookup->engine_displacement_cc,
            'engine_power_kw' => $lookup->engine_power_kw,
            'max_mass_kg' => $lookup->max_mass_kg,
        ];
    }
}
