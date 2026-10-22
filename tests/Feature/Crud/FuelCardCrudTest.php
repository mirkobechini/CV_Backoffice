<?php

namespace Tests\Feature\Crud;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\FuelCard;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelCardCrudTest extends TestCase
{
    use RefreshDatabase;

    private function group(string $name = 'Gruppo A'): Group
    {
        return Group::create(['name' => $name, 'invite_code' => Group::generateInviteCode()]);
    }

    private function capo(Group $group): User
    {
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);

        return $user;
    }

    private function vehicle(Group $group, string $internalCode = '0001'): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat '.$internalCode]);
        $model = CarModel::create(['name' => 'Ducato '.$internalCode, 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza '.$internalCode, 'needs_oxygen_check' => true]);

        return Vehicle::create([
            'license_plate' => 'AB'.rand(100, 999).'CD',
            'internal_code' => $internalCode,
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group->id,
        ]);
    }

    public function test_index_page_is_reachable(): void
    {
        $group = $this->group();
        $user = $this->capo($group);

        $response = $this->actingAs($user)->get(route('admin.fuel-cards.index'));

        $response->assertOk();
    }

    public function test_can_be_stored_as_assigned(): void
    {
        $group = $this->group();
        $user = $this->capo($group);
        $vehicle = $this->vehicle($group);

        $response = $this->actingAs($user)->post(route('admin.fuel-cards.store'), [
            'card_number' => 'FC-001',
            'provider' => 'Eni',
            'status' => FuelCard::STATUS_ASSIGNED,
            'vehicle_id' => $vehicle->id,
        ]);

        $card = FuelCard::first();
        $response->assertRedirect(route('admin.fuel-cards.show', $card));
        $this->assertDatabaseHas('fuel_cards', [
            'id' => $card->id,
            'status' => FuelCard::STATUS_ASSIGNED,
            'vehicle_id' => $vehicle->id,
            'group_id' => $group->id,
        ]);
    }

    public function test_can_be_stored_as_jolly_without_vehicle(): void
    {
        $group = $this->group();
        $user = $this->capo($group);

        $response = $this->actingAs($user)->post(route('admin.fuel-cards.store'), [
            'card_number' => 'FC-JOLLY',
            'status' => FuelCard::STATUS_JOLLY,
        ]);

        $card = FuelCard::first();
        $response->assertRedirect(route('admin.fuel-cards.show', $card));
        $this->assertDatabaseHas('fuel_cards', [
            'id' => $card->id,
            'status' => FuelCard::STATUS_JOLLY,
            'vehicle_id' => null,
        ]);
    }

    public function test_jolly_and_unassigned_are_distinct_statuses(): void
    {
        $group = $this->group();
        $user = $this->capo($group);

        $this->actingAs($user)->post(route('admin.fuel-cards.store'), [
            'card_number' => 'FC-JOLLY',
            'status' => FuelCard::STATUS_JOLLY,
        ]);
        $this->actingAs($user)->post(route('admin.fuel-cards.store'), [
            'card_number' => 'FC-PENDING',
            'status' => FuelCard::STATUS_UNASSIGNED,
        ]);

        $this->assertDatabaseHas('fuel_cards', ['card_number' => 'FC-JOLLY', 'status' => 'jolly']);
        $this->assertDatabaseHas('fuel_cards', ['card_number' => 'FC-PENDING', 'status' => 'unassigned']);
        $this->assertSame(2, FuelCard::count());
    }

    public function test_assigned_status_requires_a_vehicle(): void
    {
        $group = $this->group();
        $user = $this->capo($group);

        $response = $this->actingAs($user)->post(route('admin.fuel-cards.store'), [
            'card_number' => 'FC-001',
            'status' => FuelCard::STATUS_ASSIGNED,
        ]);

        $response->assertSessionHasErrors('vehicle_id');
    }

    public function test_jolly_status_rejects_a_vehicle(): void
    {
        $group = $this->group();
        $user = $this->capo($group);
        $vehicle = $this->vehicle($group);

        $response = $this->actingAs($user)->post(route('admin.fuel-cards.store'), [
            'card_number' => 'FC-001',
            'status' => FuelCard::STATUS_JOLLY,
            'vehicle_id' => $vehicle->id,
        ]);

        $response->assertSessionHasErrors('vehicle_id');
    }

    public function test_patch_omitting_vehicle_id_clears_it_when_switching_to_jolly(): void
    {
        // Audit 2026-10-22 (B9): un PATCH che omette del tutto vehicle_id
        // (non solo vuoto, come fa sempre il form reale) lasciava invariato
        // il valore esistente: update() aggiorna solo le chiavi presenti.
        $group = $this->group();
        $user = $this->capo($group);
        $vehicle = $this->vehicle($group);
        $card = FuelCard::create([
            'card_number' => 'FC-001',
            'status' => FuelCard::STATUS_ASSIGNED,
            'vehicle_id' => $vehicle->id,
            'group_id' => $group->id,
        ]);

        $response = $this->actingAs($user)->put(route('admin.fuel-cards.update', $card), [
            'card_number' => 'FC-001',
            'status' => FuelCard::STATUS_JOLLY,
            // vehicle_id intenzionalmente assente dal payload.
        ]);

        $response->assertRedirect(route('admin.fuel-cards.show', $card));
        $this->assertDatabaseHas('fuel_cards', [
            'id' => $card->id,
            'status' => FuelCard::STATUS_JOLLY,
            'vehicle_id' => null,
        ]);
    }

    public function test_retired_status_keeps_the_vehicle(): void
    {
        // Segnalato da Mirko: quando una tessera viene sostituita deve
        // restare traccia del veicolo a cui era assegnata, a differenza di
        // jolly/non assegnata (che non hanno mai un veicolo).
        $group = $this->group();
        $user = $this->capo($group);
        $vehicle = $this->vehicle($group);
        $card = FuelCard::create([
            'card_number' => 'FC-001',
            'status' => FuelCard::STATUS_ASSIGNED,
            'vehicle_id' => $vehicle->id,
            'group_id' => $group->id,
        ]);

        $response = $this->actingAs($user)->put(route('admin.fuel-cards.update', $card), [
            'card_number' => 'FC-001',
            'status' => FuelCard::STATUS_RETIRED,
            'vehicle_id' => $vehicle->id,
        ]);

        $response->assertRedirect(route('admin.fuel-cards.show', $card));
        $this->assertDatabaseHas('fuel_cards', [
            'id' => $card->id,
            'status' => FuelCard::STATUS_RETIRED,
            'vehicle_id' => $vehicle->id,
        ]);
    }

    public function test_retired_status_does_not_require_a_vehicle(): void
    {
        $group = $this->group();
        $user = $this->capo($group);

        $response = $this->actingAs($user)->post(route('admin.fuel-cards.store'), [
            'card_number' => 'FC-OLD',
            'status' => FuelCard::STATUS_RETIRED,
        ]);

        $card = FuelCard::first();
        $response->assertRedirect(route('admin.fuel-cards.show', $card));
        $this->assertDatabaseHas('fuel_cards', ['id' => $card->id, 'status' => FuelCard::STATUS_RETIRED, 'vehicle_id' => null]);
    }

    public function test_unassigned_card_is_not_visible_to_another_group(): void
    {
        $groupA = $this->group('Gruppo A');
        $groupB = $this->group('Gruppo B');
        $userB = $this->capo($groupB);
        $card = FuelCard::create([
            'card_number' => 'FC-001',
            'status' => FuelCard::STATUS_UNASSIGNED,
            'group_id' => $groupA->id,
        ]);

        $response = $this->actingAs($userB)->get(route('admin.fuel-cards.show', $card));

        $response->assertForbidden();
    }

    public function test_can_be_deleted(): void
    {
        $group = $this->group();
        $user = $this->capo($group);
        $card = FuelCard::create([
            'card_number' => 'FC-001',
            'status' => FuelCard::STATUS_UNASSIGNED,
            'group_id' => $group->id,
        ]);

        $response = $this->actingAs($user)->delete(route('admin.fuel-cards.destroy', $card));

        $response->assertRedirect(route('admin.fuel-cards.index'));
        $this->assertDatabaseMissing('fuel_cards', ['id' => $card->id]);
    }
}
