<?php

namespace Tests\Feature;

use App\Mail\GroupInviteMail;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GroupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_user_groups(): void
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($user, Group::ROLE_CAPO);

        $response = $this->actingAs($user)->get(route('admin.groups.index'));
        $response->assertOk();
        $response->assertSee('Gruppo A');
    }

    public function test_create_group_assigns_current_user_as_capo(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.groups.store'), [
            'name' => 'Nuovo Gruppo',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('groups', ['name' => 'Nuovo Gruppo']);
        $this->assertDatabaseHas('group_user', [
            'user_id' => $user->id,
            'role' => Group::ROLE_CAPO,
        ]);
    }

    public function test_join_group_with_valid_code(): void
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);

        $response = $this->actingAs($user)->post(route('admin.groups.join'), [
            'invite_code' => 'AAAA1111',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('group_user', [
            'user_id' => $user->id,
            'group_id' => $group->id,
            'role' => Group::ROLE_MEMBER,
        ]);
    }

    public function test_join_group_with_invalid_code_fails(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('admin.groups.join'), [
            'invite_code' => 'INVALID',
        ]);

        $response->assertSessionHasErrors('invite_code');
    }

    public function test_capo_can_update_member_role(): void
    {
        $capo = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);
        $group->addUser($member, Group::ROLE_MEMBER);

        $response = $this->actingAs($capo)->patch(route('admin.groups.role', [$group, $member]), [
            'role' => 'sottocapo',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('group_user', [
            'user_id' => $member->id,
            'group_id' => $group->id,
            'role' => 'sottocapo',
        ]);
    }

    public function test_member_cannot_update_roles(): void
    {
        $capo = User::factory()->create();
        $member = User::factory()->create();
        $other = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);
        $group->addUser($member, Group::ROLE_MEMBER);
        $group->addUser($other, Group::ROLE_MEMBER);

        $response = $this->actingAs($member)->patch(route('admin.groups.role', [$group, $other]), [
            'role' => 'sottocapo',
        ]);

        $response->assertForbidden();
    }

    public function test_capo_can_remove_member(): void
    {
        $capo = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);
        $group->addUser($member, Group::ROLE_MEMBER);

        $response = $this->actingAs($capo)->delete(route('admin.groups.remove-member', [$group, $member]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('group_user', [
            'user_id' => $member->id,
            'group_id' => $group->id,
        ]);
    }

    public function test_cannot_remove_capo(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);

        $response = $this->actingAs($capo)->delete(route('admin.groups.remove-member', [$group, $capo]));

        $response->assertForbidden();
    }

    public function test_user_cannot_access_other_group(): void
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);

        $response = $this->actingAs($user)->get(route('admin.groups.show', $group));

        $response->assertForbidden();
    }

    public function test_capo_can_regenerate_invite_code(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);

        $response = $this->actingAs($capo)->patch(route('admin.groups.invite-code', $group));

        $response->assertRedirect();
        $this->assertNotEquals('AAAA1111', $group->fresh()->invite_code);
        $this->assertNotNull($group->fresh()->invite_code);
    }

    public function test_member_cannot_regenerate_invite_code(): void
    {
        $capo = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);
        $group->addUser($member, Group::ROLE_MEMBER);

        $response = $this->actingAs($member)->patch(route('admin.groups.invite-code', $group));

        $response->assertForbidden();
    }

    public function test_cannot_update_role_of_user_not_in_group(): void
    {
        $capo = User::factory()->create();
        $outsider = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);

        $response = $this->actingAs($capo)->patch(route('admin.groups.role', [$group, $outsider]), [
            'role' => 'sottocapo',
        ]);

        $response->assertNotFound();
    }

    public function test_cannot_remove_user_not_in_group(): void
    {
        $capo = User::factory()->create();
        $outsider = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);

        $response = $this->actingAs($capo)->delete(route('admin.groups.remove-member', [$group, $outsider]));

        $response->assertNotFound();
    }

    public function test_destroy_group_requires_vehicle_confirmation(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);

        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group->id,
        ]);

        // Senza conferma, l'eliminazione viene bloccata
        $response = $this->actingAs($capo)->delete(route('admin.groups.destroy', $group));
        $response->assertSessionHasErrors('delete_vehicles');
        $this->assertDatabaseHas('groups', ['id' => $group->id]);
    }

    public function test_destroy_group_with_vehicle_confirmation_deletes_vehicles(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);

        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group->id,
        ]);

        // Con conferma, il gruppo e i veicoli vengono eliminati
        $response = $this->actingAs($capo)->delete(route('admin.groups.destroy', $group), [
            'delete_vehicles' => '1',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }

    public function test_destroy_group_without_vehicles_succeeds(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);

        $response = $this->actingAs($capo)->delete(route('admin.groups.destroy', $group));

        $response->assertRedirect();
        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    }

    public function test_capo_can_send_invite_via_email(): void
    {
        Mail::fake();
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);

        $response = $this->actingAs($capo)->post(route('admin.groups.invite', $group), [
            'email' => 'invitato@example.com',
        ]);

        $response->assertRedirect();
        Mail::assertSent(GroupInviteMail::class, function ($mail) {
            return $mail->hasTo('invitato@example.com');
        });
    }

    public function test_member_cannot_send_invite(): void
    {
        Mail::fake();
        $capo = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);
        $group->addUser($member, Group::ROLE_MEMBER);

        $response = $this->actingAs($member)->post(route('admin.groups.invite', $group), [
            'email' => 'invitato@example.com',
        ]);

        $response->assertForbidden();
        Mail::assertNothingSent();
    }
}
