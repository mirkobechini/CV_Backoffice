<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\Issue;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileUploadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
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

    public function test_vehicle_registration_card_upload(): void
    {
        Storage::fake('public');
        $user = $this->admin();
        $deps = $this->vehicleDeps();

        $file = UploadedFile::fake()->create('carta.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $deps['brand']->id,
            'car_model_id' => $deps['model']->id,
            'vehicle_type_id' => $deps['type']->id,
            'immatricolation_date' => '2024-01-01',
            'registration_card' => $file,
        ]);

        $response->assertRedirect();
        $vehicle = Vehicle::where('license_plate', 'AB123CD')->first();
        $this->assertNotNull($vehicle->registration_card_path);
        Storage::disk('public')->assertExists($vehicle->registration_card_path);
    }

    public function test_vehicle_registration_card_rejects_invalid_type(): void
    {
        Storage::fake('public');
        $user = $this->admin();
        $deps = $this->vehicleDeps();

        $file = UploadedFile::fake()->create('carta.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $deps['brand']->id,
            'car_model_id' => $deps['model']->id,
            'vehicle_type_id' => $deps['type']->id,
            'immatricolation_date' => '2024-01-01',
            'registration_card' => $file,
        ]);

        $response->assertSessionHasErrors('registration_card');
    }

    public function test_issue_image_upload(): void
    {
        Storage::fake('public');
        $user = $this->admin();
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

        $image = UploadedFile::fake()->create('guasto.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($user)->post(route('admin.issues.store'), [
            'vehicle_id' => $vehicle->id,
            'description' => 'Motore non parte',
            'event_date' => '2024-01-01',
            'status' => 'open',
            'image' => $image,
        ]);

        $response->assertRedirect();
        $issue = Issue::where('description', 'Motore non parte')->first();
        $this->assertNotNull($issue->photo);
        Storage::disk('public')->assertExists($issue->photo);
    }

    public function test_issue_image_rejects_oversized_file(): void
    {
        Storage::fake('public');
        $user = $this->admin();
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

        $image = UploadedFile::fake()->create('guasto.jpg', 3000, 'image/jpeg'); // > 2048 KB

        $response = $this->actingAs($user)->post(route('admin.issues.store'), [
            'vehicle_id' => $vehicle->id,
            'description' => 'Motore non parte',
            'event_date' => '2024-01-01',
            'status' => 'open',
            'image' => $image,
        ]);

        $response->assertSessionHasErrors('image');
    }
}
