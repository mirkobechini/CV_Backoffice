<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFleetStatusTest extends TestCase
{
    use RefreshDatabase;

    private function groupWithCapo(): array
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo ' . uniqid(), 'invite_code' => Group::generateInviteCode()]);
        $group->addUser($capo, Group::ROLE_CAPO);

        return [$capo, $group];
    }

    private function createVehicle(Group $group, array $overrides = []): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat ' . uniqid()]);
        $carModel = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $vehicleType = VehicleType::create(['name' => 'Ambulanza ' . uniqid(), 'first_inspection_months' => 12, 'regular_inspection_months' => 12]);

        return Vehicle::create(array_merge([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'vehicle_type_id' => $vehicleType->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group->id,
        ], $overrides));
    }

    public function test_capo_can_generate_public_status_link(): void
    {
        [$capo, $group] = $this->groupWithCapo();

        $response = $this->actingAs($capo)->patch(route('admin.groups.public-status-token.generate', $group));

        $response->assertRedirect();
        $this->assertNotNull($group->fresh()->public_status_token);
    }

    public function test_member_cannot_generate_public_status_link(): void
    {
        [, $group] = $this->groupWithCapo();
        $member = User::factory()->create();
        $group->addUser($member, Group::ROLE_MEMBER);

        $response = $this->actingAs($member)->patch(route('admin.groups.public-status-token.generate', $group));

        $response->assertForbidden();
        $this->assertNull($group->fresh()->public_status_token);
    }

    public function test_capo_can_revoke_public_status_link(): void
    {
        [$capo, $group] = $this->groupWithCapo();
        $group->update(['public_status_token' => Group::generatePublicStatusToken()]);

        $response = $this->actingAs($capo)->delete(route('admin.groups.public-status-token.revoke', $group));

        $response->assertRedirect();
        $this->assertNull($group->fresh()->public_status_token);
    }

    public function test_regenerating_invalidates_previous_link(): void
    {
        [$capo, $group] = $this->groupWithCapo();
        $group->update(['public_status_token' => 'old-token-value']);

        $this->actingAs($capo)->patch(route('admin.groups.public-status-token.generate', $group));

        $this->assertNotSame('old-token-value', $group->fresh()->public_status_token);
        $this->get(route('public.fleet-status', 'old-token-value'))->assertNotFound();
    }

    public function test_public_page_shows_vehicle_availability_without_auth(): void
    {
        [, $group] = $this->groupWithCapo();
        $group->update(['public_status_token' => Group::generatePublicStatusToken()]);

        $available = $this->createVehicle($group, ['internal_code' => '0001']);
        $inWorkshop = $this->createVehicle($group, ['license_plate' => 'XY987ZZ', 'internal_code' => '0002']);

        $provider = Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);
        MaintenanceRecord::create([
            'vehicle_id' => $inWorkshop->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);

        $response = $this->get(route('public.fleet-status', $group->public_status_token));

        $response->assertOk();
        $response->assertSee('0001');
        $response->assertSee('0002');
        $response->assertSee('Disponibile');
        $response->assertSee('In officina');
        // Nessun dato sensibile: niente targhe né descrizioni di guasti.
        $response->assertDontSee('AB123CD');
        $response->assertDontSee('XY987ZZ');
    }

    public function test_public_page_returns_404_for_invalid_token(): void
    {
        $response = $this->get(route('public.fleet-status', 'not-a-real-token'));

        $response->assertNotFound();
    }

    public function test_public_page_only_shows_vehicles_from_its_own_group(): void
    {
        [, $groupA] = $this->groupWithCapo();
        $groupA->update(['public_status_token' => Group::generatePublicStatusToken()]);
        $this->createVehicle($groupA, ['internal_code' => 'A-VEH']);

        [$capoB, $groupB] = $this->groupWithCapo();
        $this->createVehicle($groupB, ['license_plate' => 'ZZ999ZZ', 'internal_code' => 'B-VEH']);

        $response = $this->get(route('public.fleet-status', $groupA->public_status_token));

        $response->assertOk();
        $response->assertSee('A-VEH');
        $response->assertDontSee('B-VEH');
    }
}
