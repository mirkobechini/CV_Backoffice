<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Storage/promozione/sicurezza della carta di circolazione, estratti da
 * VehicleScanTest.php (era a 514 righe, oltre la soglia ~500 di AGENTS.md)
 * per non fargliela superare — audit qualità 2026-11-25, F3.
 */
class VehicleRegistrationCardTest extends TestCase
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

    private function vehicleDeps(): array
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);

        return compact('brand', 'model', 'type');
    }

    private function fakeChatCompletion(array $fields): void
    {
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode($fields)]],
                ],
            ]),
        ]);
    }

    private function extractedFieldsStub(array $overrides = []): array
    {
        return array_merge([
            'license_plate' => 'AB123CD',
            'immatricolation_date' => '2024-01-01',
            'fuel_type' => 'diesel',
            'brand' => 'Fiat',
            'car_model' => 'Ducato',
            'vin' => 'ZFA1234567890',
            'color' => 'Bianco',
            'seats' => 5,
            'environmental_class' => 'Euro 6',
            'max_mass_kg' => 3500,
            'engine_displacement_cc' => 2287,
            'engine_power_kw' => 96,
            'vehicle_category' => 'M1',
            'allowed_tire_sizes' => [['size' => '225/75R16C']],
            'has_timing_belt_suggested' => true,
            'type_code' => '250',
            'variant_code' => 'AENRA',
            'version_code' => 'GE9M',
        ], $overrides);
    }

    public function test_store_promotes_pending_scanned_photo_into_registration_card(): void
    {
        Storage::fake('public');
        $user = $this->capo();
        $deps = $this->vehicleDeps();

        $pendingPath = UploadedFile::fake()->create('libretto.jpg', 100, 'image/jpeg')
            ->storeAs('registration_cards/pending', 'abc123.jpg', 'public');

        $response = $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $deps['brand']->id,
            'car_model_id' => $deps['model']->id,
            'vehicle_type_id' => $deps['type']->id,
            'immatricolation_date' => '2024-01-01',
            'scanned_registration_card_path' => $pendingPath,
        ]);

        $response->assertRedirect();
        $vehicle = Vehicle::where('license_plate', 'AB123CD')->first();
        $this->assertNotNull($vehicle->registration_card_path);
        $this->assertStringStartsWith('registration_cards/', $vehicle->registration_card_path);
        $this->assertStringNotContainsString('pending', $vehicle->registration_card_path);
        Storage::disk('public')->assertExists($vehicle->registration_card_path);
        Storage::disk('public')->assertMissing($pendingPath);
    }

    public function test_update_promotes_pending_scanned_photo_into_registration_card(): void
    {
        Storage::fake('public');
        $user = $this->capo();
        $deps = $this->vehicleDeps();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $deps['brand']->id,
            'car_model_id' => $deps['model']->id,
            'vehicle_type_id' => $deps['type']->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $user->activeGroup()->id,
        ]);

        $pendingPath = UploadedFile::fake()->create('libretto.jpg', 100, 'image/jpeg')
            ->storeAs('registration_cards/pending', 'abc123.jpg', 'public');

        $response = $this->actingAs($user)->put(route('admin.vehicles.update', $vehicle), [
            'license_plate' => $vehicle->license_plate,
            'internal_code' => $vehicle->internal_code,
            'brand_id' => $deps['brand']->id,
            'car_model_id' => $deps['model']->id,
            'vehicle_type_id' => $deps['type']->id,
            'immatricolation_date' => '2024-01-01',
            'scanned_registration_card_path' => $pendingPath,
        ]);

        $response->assertRedirect();
        $vehicle->refresh();
        $this->assertNotNull($vehicle->registration_card_path);
        $this->assertStringStartsWith('registration_cards/', $vehicle->registration_card_path);
        $this->assertStringNotContainsString('pending', $vehicle->registration_card_path);
        Storage::disk('public')->assertExists($vehicle->registration_card_path);
        Storage::disk('public')->assertMissing($pendingPath);
    }

    public function test_registration_card_uses_configured_uploads_disk(): void
    {
        config(['filesystems.uploads_disk' => 's3']);
        Storage::fake('s3');
        Storage::fake('public');
        $user = $this->capo();
        $deps = $this->vehicleDeps();

        $response = $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $deps['brand']->id,
            'car_model_id' => $deps['model']->id,
            'vehicle_type_id' => $deps['type']->id,
            'immatricolation_date' => '2024-01-01',
            'registration_card' => UploadedFile::fake()->create('libretto.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertRedirect();
        $vehicle = Vehicle::where('license_plate', 'AB123CD')->first();
        $this->assertNotNull($vehicle->registration_card_path);
        Storage::disk('s3')->assertExists($vehicle->registration_card_path);
        Storage::disk('public')->assertMissing($vehicle->registration_card_path);
    }

    public function test_registration_card_stores_with_extension_guessed_from_content_not_client_name(): void
    {
        // Audit sicurezza SL2: l'estensione salvata veniva presa dal nome
        // file dichiarato dal client (spoofabile), non dal contenuto reale.
        // File dichiarato "libretto.png" ma con mime reale image/jpeg: il
        // nome salvato deve riflettere jpg, non il png dichiarato dal client.
        Storage::fake(config('filesystems.uploads_disk'));
        $user = $this->capo();
        $deps = $this->vehicleDeps();

        $response = $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $deps['brand']->id,
            'car_model_id' => $deps['model']->id,
            'vehicle_type_id' => $deps['type']->id,
            'immatricolation_date' => '2024-01-01',
            'registration_card' => UploadedFile::fake()->create('libretto.png', 100, 'image/jpeg'),
        ]);

        $response->assertRedirect();
        $vehicle = Vehicle::where('license_plate', 'AB123CD')->first();
        $this->assertStringEndsWith('.jpg', $vehicle->registration_card_path);
    }

    public function test_registration_card_rejects_file_larger_than_8mb(): void
    {
        $user = $this->capo();
        $deps = $this->vehicleDeps();

        $response = $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $deps['brand']->id,
            'car_model_id' => $deps['model']->id,
            'vehicle_type_id' => $deps['type']->id,
            'immatricolation_date' => '2024-01-01',
            'registration_card' => UploadedFile::fake()->create('libretto.jpg', 8193, 'image/jpeg'),
        ]);

        $response->assertSessionHasErrors('registration_card');
        $this->assertDatabaseMissing('vehicles', ['license_plate' => 'AB123CD']);
    }

    public function test_scan_endpoint_works_when_uploads_disk_is_s3(): void
    {
        config(['filesystems.uploads_disk' => 's3']);
        Storage::fake('s3');
        $user = $this->capo();

        $this->fakeChatCompletion($this->extractedFieldsStub());

        $response = $this->actingAs($user)->post(route('admin.vehicles.scan-libretto'), [
            'photo_front' => UploadedFile::fake()->create('fronte.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertRedirect(route('admin.vehicles.create'));
        $response->assertSessionHasInput('license_plate', 'AB123CD');
        $pendingPath = $response->getSession()->get('scanned_registration_card_path');
        Storage::disk('s3')->assertExists($pendingPath);
    }

    public function test_store_ignores_tampered_scanned_registration_card_path(): void
    {
        Storage::fake('public');
        $user = $this->capo();
        $deps = $this->vehicleDeps();

        $response = $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $deps['brand']->id,
            'car_model_id' => $deps['model']->id,
            'vehicle_type_id' => $deps['type']->id,
            'immatricolation_date' => '2024-01-01',
            'scanned_registration_card_path' => '../../etc/passwd',
        ]);

        $response->assertRedirect();
        $vehicle = Vehicle::where('license_plate', 'AB123CD')->first();
        $this->assertNull($vehicle->registration_card_path);
    }

    public function test_store_rejects_path_traversal_disguised_as_a_pending_scan(): void
    {
        // Falla di sicurezza critica corretta nell'audit 2026-10-22: a
        // differenza del test sopra (prefisso ovviamente sbagliato), questo
        // path PASSA il controllo startsWith('registration_cards/pending/')
        // che c'era prima — Flysystem normalizza ".." restando comunque
        // dentro la root del disco, quindi risolveva a un file fuori dalla
        // cartella pending (qui: un backup dell'intero database
        // multi-tenant), "promuovendolo" sul veicolo e rendendolo
        // scaricabile tramite l'URL firmato della carta di circolazione.
        Storage::fake('public');
        Storage::disk('public')->put('backups/backup-2026-10-22.json', '{"secret":"all tenants data"}');
        $user = $this->capo();
        $deps = $this->vehicleDeps();

        $response = $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $deps['brand']->id,
            'car_model_id' => $deps['model']->id,
            'vehicle_type_id' => $deps['type']->id,
            'immatricolation_date' => '2024-01-01',
            'scanned_registration_card_path' => 'registration_cards/pending/../../backups/backup-2026-10-22.json',
        ]);

        $response->assertRedirect();
        $vehicle = Vehicle::where('license_plate', 'AB123CD')->first();
        $this->assertNull($vehicle->registration_card_path);
        // Il backup non deve essere stato spostato/rinominato.
        Storage::disk('public')->assertExists('backups/backup-2026-10-22.json');
    }
}
