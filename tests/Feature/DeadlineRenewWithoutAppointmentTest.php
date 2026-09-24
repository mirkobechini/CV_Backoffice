<?php

namespace Tests\Feature;

use App\Models\Deadline;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un veicolo acquistato usato può avere l'ultima revisione/tagliando/
 * cinghia già effettuati dal precedente proprietario, prima che questo
 * sistema iniziasse a tracciarlo: "Rinnova adesso" (DeadlineController::renew,
 * DeadlineService::renewWithoutAppointment) permette di registrarlo senza
 * dover creare un appuntamento in officina fittizio.
 */
class DeadlineRenewWithoutAppointmentTest extends TestCase
{
    use RefreshDatabase;

    private function group(): Group
    {
        return Group::create(['name' => 'Gruppo A', 'invite_code' => Group::generateInviteCode()]);
    }

    private function vehicle(Group $group, array $overrides = []): Vehicle
    {
        $vt = VehicleType::create(array_merge([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
            'regular_tagliando_km' => 20000,
        ], $overrides));

        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '1234',
            'vehicle_type_id' => $vt->id,
            'immatricolation_date' => '2020-01-01',
            'has_timing_belt' => true,
            'group_id' => $group->id,
        ]);
    }

    public function test_capo_can_renew_ministerial_deadline_without_appointment(): void
    {
        $group = $this->group();
        $capo = User::factory()->create();
        $group->addUser($capo, Group::ROLE_CAPO);
        $vehicle = $this->vehicle($group);
        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => now()->subDays(5),
            'status' => Deadline::STATUS_EXPIRED,
            'is_renewed' => false,
        ]);

        $response = $this->actingAs($capo)->patch(route('admin.deadlines.renew', $deadline), [
            'renewed_date' => '2026-06',
        ]);

        $response->assertRedirect();
        $deadline->refresh();
        $this->assertTrue($deadline->is_renewed);
        $this->assertSame(Deadline::STATUS_RENEWED, $deadline->status);

        $next = Deadline::where('renews_deadline_id', $deadline->id)->first();
        $this->assertNotNull($next);
        $this->assertSame('2028-06-30', $next->due_date->toDateString());
    }

    public function test_renewing_tagliando_sets_km_interval_on_next_occurrence(): void
    {
        $group = $this->group();
        $capo = User::factory()->create();
        $group->addUser($capo, Group::ROLE_CAPO);
        $vehicle = $this->vehicle($group);
        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => now()->subDays(5),
            'status' => Deadline::STATUS_EXPIRED,
            'is_renewed' => false,
        ]);

        $this->actingAs($capo)->patch(route('admin.deadlines.renew', $deadline), [
            'renewed_date' => '2026-06',
            'mileage' => 85000,
        ])->assertRedirect();

        $deadline->refresh();
        $this->assertSame(85000, $deadline->last_mileage);

        $next = Deadline::where('renews_deadline_id', $deadline->id)->first();
        $this->assertNotNull($next);
        $this->assertSame('2027-06-30', $next->due_date->toDateString());
        $this->assertSame(85000, $next->last_mileage);
        $this->assertSame(20000, $next->interval_km);

        $this->assertDatabaseHas('mileage_logs', [
            'vehicle_id' => $vehicle->id,
            'mileage' => 85000,
        ]);
    }

    public function test_renewing_cinghia_uses_fixed_interval(): void
    {
        $group = $this->group();
        $capo = User::factory()->create();
        $group->addUser($capo, Group::ROLE_CAPO);
        $vehicle = $this->vehicle($group);
        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
            'due_date' => now()->subDays(5),
            'status' => Deadline::STATUS_EXPIRED,
            'is_renewed' => false,
        ]);

        $this->actingAs($capo)->patch(route('admin.deadlines.renew', $deadline), [
            'renewed_date' => '2026-06',
            'mileage' => 60000,
        ])->assertRedirect();

        $next = Deadline::where('renews_deadline_id', $deadline->id)->first();
        $this->assertNotNull($next);
        $this->assertSame(100000, $next->interval_km);
        $this->assertSame(3650, $next->interval_days);
        $this->assertSame(60000, $next->last_mileage);
    }

    public function test_renew_rejects_insurance_deadline(): void
    {
        $group = $this->group();
        $capo = User::factory()->create();
        $group->addUser($capo, Group::ROLE_CAPO);
        $vehicle = $this->vehicle($group);
        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'due_date' => now()->subDays(5),
            'status' => Deadline::STATUS_EXPIRED,
            'is_renewed' => false,
        ]);

        $this->actingAs($capo)->patch(route('admin.deadlines.renew', $deadline), [
            'renewed_date' => '2026-06',
        ])->assertStatus(400);

        $this->assertFalse($deadline->fresh()->is_renewed);
    }

    public function test_member_cannot_renew_deadline(): void
    {
        $group = $this->group();
        $member = User::factory()->create();
        $group->addUser($member, Group::ROLE_MEMBER);
        $vehicle = $this->vehicle($group);
        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => now()->subDays(5),
            'status' => Deadline::STATUS_EXPIRED,
            'is_renewed' => false,
        ]);

        $this->actingAs($member)->patch(route('admin.deadlines.renew', $deadline), [
            'renewed_date' => '2026-06',
        ])->assertForbidden();

        $this->assertFalse($deadline->fresh()->is_renewed);
    }

    public function test_cannot_renew_another_groups_deadline(): void
    {
        $groupA = $this->group();
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => Group::generateInviteCode()]);
        $capoA = User::factory()->create();
        $groupA->addUser($capoA, Group::ROLE_CAPO);
        $vehicleB = $this->vehicle($groupB);
        $deadline = Deadline::create([
            'vehicle_id' => $vehicleB->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => now()->subDays(5),
            'status' => Deadline::STATUS_EXPIRED,
            'is_renewed' => false,
        ]);

        $this->actingAs($capoA)->patch(route('admin.deadlines.renew', $deadline), [
            'renewed_date' => '2026-06',
        ])->assertForbidden();

        $this->assertFalse($deadline->fresh()->is_renewed);
    }

    public function test_show_page_offers_renew_button_only_for_unrenewed_renewable_types(): void
    {
        $group = $this->group();
        $capo = User::factory()->create();
        $group->addUser($capo, Group::ROLE_CAPO);
        $vehicle = $this->vehicle($group);

        $renewable = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_MINISTERIAL,
            'due_date' => now()->addDays(30),
            'status' => Deadline::STATUS_PENDING,
            'is_renewed' => false,
        ]);
        $insurance = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'due_date' => now()->addDays(30),
            'status' => Deadline::STATUS_PENDING,
            'is_renewed' => false,
        ]);

        $this->actingAs($capo)->get(route('admin.deadlines.show', $renewable))
            ->assertOk()
            ->assertSee('Rinnova adesso');

        $this->actingAs($capo)->get(route('admin.deadlines.show', $insurance))
            ->assertOk()
            ->assertDontSee('Rinnova adesso');
    }
}
