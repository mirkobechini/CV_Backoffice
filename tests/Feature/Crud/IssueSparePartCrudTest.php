<?php

namespace Tests\Feature\Crud;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\Issue;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueSparePartCrudTest extends TestCase
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

    private function member(Group $group): User
    {
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_MEMBER);

        return $user;
    }

    private function issue(Group $group, string $internalCode = '0001'): Issue
    {
        $brand = Brand::create(['name' => 'Fiat '.$internalCode]);
        $model = CarModel::create(['name' => 'Ducato '.$internalCode, 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza '.$internalCode, 'needs_oxygen_check' => true]);
        $vehicle = Vehicle::create([
            'license_plate' => 'AB'.rand(100, 999).'CD',
            'internal_code' => $internalCode,
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group->id,
        ]);

        return Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Specchietto rotto',
            'status' => 'in_progress',
            'event_date' => '2026-01-01',
        ]);
    }

    public function test_capo_can_add_multiple_spare_parts_at_once(): void
    {
        $group = $this->group();
        $user = $this->capo($group);
        $issue = $this->issue($group);

        $response = $this->actingAs($user)->post(route('admin.issues.spare-parts.store', $issue), [
            'parts' => [
                ['name' => 'Specchietto destro', 'ordered_at' => '2026-01-10'],
                ['name' => 'Vite di fissaggio'],
            ],
        ]);

        $response->assertRedirect(route('admin.issues.show', $issue));
        $this->assertDatabaseHas('issue_spare_parts', ['issue_id' => $issue->id, 'name' => 'Specchietto destro', 'ordered_at' => '2026-01-10 00:00:00']);
        // La riga senza data ordine eredita quella della prima riga compilata.
        $this->assertDatabaseHas('issue_spare_parts', ['issue_id' => $issue->id, 'name' => 'Vite di fissaggio', 'ordered_at' => '2026-01-10 00:00:00']);
    }

    public function test_part_can_be_ordered_without_an_arrival_date(): void
    {
        $group = $this->group();
        $user = $this->capo($group);
        $issue = $this->issue($group);

        $response = $this->actingAs($user)->post(route('admin.issues.spare-parts.store', $issue), [
            'parts' => [
                ['name' => 'Specchietto destro', 'ordered_at' => '2026-01-10'],
            ],
        ]);

        $response->assertRedirect(route('admin.issues.show', $issue));
        $this->assertDatabaseHas('issue_spare_parts', [
            'issue_id' => $issue->id,
            'name' => 'Specchietto destro',
            'ordered_at' => '2026-01-10 00:00:00',
            'arrived_at' => null,
        ]);
    }

    public function test_badge_shows_pending_when_a_part_is_ordered_but_not_arrived(): void
    {
        $group = $this->group();
        $user = $this->capo($group);
        $issue = $this->issue($group);
        $issue->spareParts()->create(['name' => 'Specchietto destro', 'ordered_at' => '2026-01-10']);

        $response = $this->actingAs($user)->get(route('admin.issues.show', $issue));

        $response->assertOk();
        $response->assertSee('Pezzi in attesa');
    }

    public function test_badge_shows_arrived_when_all_ordered_parts_have_arrived(): void
    {
        $group = $this->group();
        $user = $this->capo($group);
        $issue = $this->issue($group);
        $issue->spareParts()->create(['name' => 'Specchietto destro', 'ordered_at' => '2026-01-10', 'arrived_at' => '2026-01-15']);

        $response = $this->actingAs($user)->get(route('admin.issues.show', $issue));

        $response->assertOk();
        $response->assertSee('Pezzi arrivati');
    }

    public function test_badge_shows_pending_when_arrival_date_is_in_the_future(): void
    {
        $group = $this->group();
        $user = $this->capo($group);
        $issue = $this->issue($group);
        $issue->spareParts()->create([
            'name' => 'Specchietto destro',
            'ordered_at' => '2026-01-10',
            'arrived_at' => now()->addDay()->toDateString(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.issues.show', $issue));

        $response->assertOk();
        $response->assertSee('Pezzi in attesa');
        $response->assertDontSee('Pezzi arrivati');
    }

    public function test_no_badge_when_no_parts_are_ordered(): void
    {
        $group = $this->group();
        $user = $this->capo($group);
        $issue = $this->issue($group);
        $issue->spareParts()->create(['name' => 'Specchietto destro']);

        $response = $this->actingAs($user)->get(route('admin.issues.show', $issue));

        $response->assertOk();
        $response->assertDontSee('Pezzi in attesa');
        $response->assertDontSee('Pezzi arrivati');
    }

    public function test_capo_can_mark_a_part_as_arrived(): void
    {
        $group = $this->group();
        $user = $this->capo($group);
        $issue = $this->issue($group);
        $part = $issue->spareParts()->create(['name' => 'Specchietto destro', 'ordered_at' => '2026-01-10']);

        $response = $this->actingAs($user)->patch(route('admin.issues.spare-parts.update', [$issue, $part]), [
            'name' => $part->name,
            'ordered_at' => '2026-01-10',
            'arrived_at' => '2026-01-15',
        ]);

        $response->assertRedirect(route('admin.issues.show', $issue));
        $this->assertDatabaseHas('issue_spare_parts', ['id' => $part->id, 'arrived_at' => '2026-01-15 00:00:00']);
    }

    public function test_capo_can_delete_a_part(): void
    {
        $group = $this->group();
        $user = $this->capo($group);
        $issue = $this->issue($group);
        $part = $issue->spareParts()->create(['name' => 'Specchietto destro']);

        $response = $this->actingAs($user)->delete(route('admin.issues.spare-parts.destroy', [$issue, $part]));

        $response->assertRedirect(route('admin.issues.show', $issue));
        $this->assertDatabaseMissing('issue_spare_parts', ['id' => $part->id]);
    }

    public function test_member_cannot_add_spare_parts(): void
    {
        $group = $this->group();
        $member = $this->member($group);
        $issue = $this->issue($group);

        $response = $this->actingAs($member)->post(route('admin.issues.spare-parts.store', $issue), [
            'parts' => [['name' => 'Specchietto destro']],
        ]);

        $response->assertForbidden();
    }

    public function test_capo_cannot_manage_parts_of_another_groups_issue(): void
    {
        $groupA = $this->group('Gruppo A');
        $groupB = $this->group('Gruppo B');
        $userB = $this->capo($groupB);
        $issue = $this->issue($groupA);

        $response = $this->actingAs($userB)->post(route('admin.issues.spare-parts.store', $issue), [
            'parts' => [['name' => 'Specchietto destro']],
        ]);

        $response->assertForbidden();
    }

    public function test_a_part_cannot_be_edited_through_another_issues_url(): void
    {
        $group = $this->group();
        $user = $this->capo($group);
        $issueA = $this->issue($group, '0001');
        $issueB = $this->issue($group, '0002');
        $part = $issueA->spareParts()->create(['name' => 'Specchietto destro']);

        $response = $this->actingAs($user)->patch(route('admin.issues.spare-parts.update', [$issueB, $part]), [
            'name' => 'Tentativo',
        ]);

        $response->assertNotFound();
    }

    public function test_show_page_lists_ordered_and_arrived_events_in_the_timeline(): void
    {
        $group = $this->group();
        $user = $this->capo($group);
        $issue = $this->issue($group);
        $part = $issue->spareParts()->create(['name' => 'Specchietto destro', 'ordered_at' => '2026-01-10']);
        $part->update(['arrived_at' => '2026-01-15']);

        $response = $this->actingAs($user)->get(route('admin.issues.show', $issue));

        $response->assertOk();
        $response->assertSee('Pezzo ordinato: Specchietto destro');
        $response->assertSee('Pezzo arrivato: Specchietto destro');
        // La data cast su una colonna 'date' viene serializzata in JSON
        // come UTC (es. "...T23:00:00Z" con APP_TIMEZONE=Europe/Rome per
        // mezzanotte locale del giorno dopo): la timeline deve riportare
        // la data corretta nel fuso dell'app, non quella UTC.
        $response->assertSee('10/01/2026');
        $response->assertSee('15/01/2026');
    }
}
