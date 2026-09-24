<?php

namespace Tests\Feature;

use App\Exceptions\VehicleScanException;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\VehicleScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleScanTest extends TestCase
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

    private function member(): User
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => Group::generateInviteCode()]);
        $group->addUser($user, Group::ROLE_MEMBER);

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
            'allowed_tire_size' => '225/75R16C',
            'has_timing_belt_suggested' => true,
        ], $overrides);
    }

    public function test_scan_service_extracts_expected_fields(): void
    {
        $this->fakeChatCompletion($this->extractedFieldsStub());

        $file = UploadedFile::fake()->create('libretto.jpg', 100, 'image/jpeg');
        $result = app(VehicleScanService::class)->scan($file->getRealPath());

        $this->assertSame('AB123CD', $result['license_plate']);
        $this->assertSame('Fiat', $result['brand']);
        $this->assertSame('225/75R16C', $result['allowed_tire_size']);
        $this->assertTrue($result['has_timing_belt_suggested']);
    }

    public function test_scan_service_throws_on_malformed_json(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'not json']],
                ],
            ]),
        ]);

        $this->expectException(VehicleScanException::class);

        $file = UploadedFile::fake()->create('libretto.jpg', 100, 'image/jpeg');
        app(VehicleScanService::class)->scan($file->getRealPath());
    }

    public function test_scan_service_throws_on_http_failure(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response(['error' => 'boom'], 500),
        ]);

        $this->expectException(VehicleScanException::class);

        $file = UploadedFile::fake()->create('libretto.jpg', 100, 'image/jpeg');
        app(VehicleScanService::class)->scan($file->getRealPath());
    }

    public function test_match_brand_and_model_exact_fuzzy_and_no_match(): void
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);

        $service = app(VehicleScanService::class);

        $exact = $service->matchBrandAndModel('Fiat', 'Ducato');
        $this->assertSame($brand->id, $exact['brand_id']);
        $this->assertSame($model->id, $exact['car_model_id']);

        $fuzzy = $service->matchBrandAndModel('FIAT', 'ducato');
        $this->assertSame($brand->id, $fuzzy['brand_id']);
        $this->assertSame($model->id, $fuzzy['car_model_id']);

        $none = $service->matchBrandAndModel('Marca Sconosciuta', 'Modello X');
        $this->assertNull($none['brand_id']);
        $this->assertNull($none['car_model_id']);
    }

    public function test_scan_endpoint_prefills_create_form_on_success(): void
    {
        Storage::fake('public');
        $user = $this->capo();
        $deps = $this->vehicleDeps();

        $this->fakeChatCompletion($this->extractedFieldsStub());

        $response = $this->actingAs($user)->post(route('admin.vehicles.scan-libretto'), [
            'photo' => UploadedFile::fake()->create('libretto.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertRedirect(route('admin.vehicles.create'));
        $response->assertSessionHas('scanned_registration_card_path');
        $response->assertSessionHas('timing_belt_suggested');
        $response->assertSessionHasInput('license_plate', 'AB123CD');
        $response->assertSessionHasInput('brand_id', $deps['brand']->id);
        $response->assertSessionHasInput('car_model_id', $deps['model']->id);
        $response->assertSessionHasInput('allowed_tire_size', '225/75R16C');
    }

    public function test_scan_endpoint_flags_unmatched_brand_and_model(): void
    {
        Storage::fake('public');
        $user = $this->capo();

        $this->fakeChatCompletion($this->extractedFieldsStub([
            'brand' => 'Marca Sconosciuta',
            'car_model' => 'Modello X',
        ]));

        $response = $this->actingAs($user)->post(route('admin.vehicles.scan-libretto'), [
            'photo' => UploadedFile::fake()->create('libretto.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertRedirect(route('admin.vehicles.create'));
        $response->assertSessionHas('unmatched_brand_text', 'Marca Sconosciuta');
        $response->assertSessionHas('unmatched_model_text', 'Modello X');
        $response->assertSessionMissing('brand_id');
    }

    public function test_scan_endpoint_keeps_photo_and_flashes_error_on_failure(): void
    {
        Storage::fake('public');
        $user = $this->capo();

        Http::fake([
            '*/chat/completions' => Http::response(['error' => 'boom'], 500),
        ]);

        $response = $this->actingAs($user)->post(route('admin.vehicles.scan-libretto'), [
            'photo' => UploadedFile::fake()->create('libretto.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertRedirect(route('admin.vehicles.create'));
        $response->assertSessionHas('error');
        $response->assertSessionHas('scanned_registration_card_path');
    }

    public function test_member_cannot_scan_registration_card(): void
    {
        Storage::fake('public');
        $user = $this->member();

        $response = $this->actingAs($user)->post(route('admin.vehicles.scan-libretto'), [
            'photo' => UploadedFile::fake()->create('libretto.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertForbidden();
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

    public function test_tire_size_mismatch_shows_warning_without_blocking_save(): void
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
            'allowed_tire_size' => '225/75R16C',
        ]);

        $response = $this->actingAs($user)->post(route('admin.tires.store'), [
            'vehicle_id' => $vehicle->id,
            'season' => 'summer',
            'position' => 'front_left',
            'size' => '205/55R16',
            'status' => 'stored',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('tire_size_warning');
        $this->assertDatabaseHas('tires', ['vehicle_id' => $vehicle->id, 'size' => '205/55R16']);
    }

    public function test_no_tire_size_warning_when_vehicle_has_no_allowed_size(): void
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

        $response = $this->actingAs($user)->post(route('admin.tires.store'), [
            'vehicle_id' => $vehicle->id,
            'season' => 'summer',
            'position' => 'front_left',
            'size' => '205/55R16',
            'status' => 'stored',
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('tire_size_warning');
    }

    public function test_tire_size_regex_rejects_invalid_format(): void
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

        $response = $this->actingAs($user)->post(route('admin.tires.store'), [
            'vehicle_id' => $vehicle->id,
            'season' => 'summer',
            'position' => 'front_left',
            'size' => 'not-a-tire-size',
            'status' => 'stored',
        ]);

        $response->assertSessionHasErrors('size');
    }
}
