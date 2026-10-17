<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Avviso non bloccante di misura/combinazione gomma non ammessa
 * (Tire::sizeMismatchWarning()) — spezzato da VehicleScanTest per restare
 * sotto ~500 righe.
 */
class TireSizeMismatchWarningTest extends TestCase
{
    use RefreshDatabase;

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
            'allowed_tire_sizes' => [['size' => '225/75R16C', 'code' => 'A1']],
        ]);

        $response = $this->actingAs($user)->post(route('admin.tires.store'), [
            'vehicle_id' => $vehicle->id,
            'season' => 'summer',
            'tire_group' => 'single',
            'position' => 'front_left',
            'size' => '205/55R16',
            'status' => 'stored',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('tire_size_warning');
        $this->assertDatabaseHas('tires', ['vehicle_id' => $vehicle->id, 'size' => '205/55R16']);
    }

    public function test_no_tire_size_warning_when_size_matches_any_of_several_allowed(): void
    {
        // Senza combinazioni ammesse definite, il controllo per assale non
        // si applica (permissivo): basta che la misura corrisponda a una
        // delle misure consigliate, indipendentemente dalla posizione.
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
            'allowed_tire_sizes' => [
                ['size' => '225/75R16C', 'code' => 'A1'],
                ['size' => '205/55R16', 'code' => 'A2'],
            ],
        ]);

        $response = $this->actingAs($user)->post(route('admin.tires.store'), [
            'vehicle_id' => $vehicle->id,
            'season' => 'summer',
            'tire_group' => 'single',
            'position' => 'front_left',
            'size' => '205/55R16',
            'status' => 'stored',
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('tire_size_warning');
    }

    public function test_tire_size_mismatch_depends_on_allowed_combinations(): void
    {
        // Un codice che nella tabella delle combinazioni ammesse compare
        // solo come rear_code (mai come front_code) non deve generare
        // avviso per una gomma posteriore, ma deve generarlo per una
        // anteriore — derivato dalle combinazioni, non da una posizione
        // scelta a priori sulla singola misura.
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
            'allowed_tire_sizes' => [
                ['size' => '205/55R16', 'code' => 'A1'],
                ['size' => '225/75R16C', 'code' => 'A2'],
            ],
            'allowed_tire_size_combinations' => [
                ['front_code' => 'A2', 'rear_code' => 'A1'],
            ],
        ]);

        $rearResponse = $this->actingAs($user)->post(route('admin.tires.store'), [
            'vehicle_id' => $vehicle->id,
            'season' => 'summer',
            'tire_group' => 'single',
            'position' => 'rear_left',
            'size' => '205/55R16',
            'status' => 'stored',
        ]);
        $rearResponse->assertSessionMissing('tire_size_warning');

        $frontResponse = $this->actingAs($user)->post(route('admin.tires.store'), [
            'vehicle_id' => $vehicle->id,
            'season' => 'summer',
            'tire_group' => 'single',
            'position' => 'front_left',
            'size' => '205/55R16',
            'status' => 'stored',
        ]);
        $frontResponse->assertSessionHas('tire_size_warning');
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
            'tire_group' => 'single',
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
            'tire_group' => 'single',
            'position' => 'front_left',
            'size' => 'not-a-tire-size',
            'status' => 'stored',
        ]);

        $response->assertSessionHasErrors('size');
    }
}
