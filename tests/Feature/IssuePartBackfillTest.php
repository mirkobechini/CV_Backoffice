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
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IssuePartBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.key' => 'test-key']);
    }

    private function admin(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    private function member(): User
    {
        return User::factory()->withRole('volunteer')->create();
    }

    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    private function vehicle(?Group $group = null): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);

        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'vehicle_type_id' => $type->id,
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => ($group ?? $this->defaultGroup())->id,
        ]);
    }

    public function test_index_lists_only_issues_without_a_part(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $withoutPart = Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Senza parte', 'status' => 'open', 'event_date' => '2026-01-01']);
        Issue::create(['vehicle_id' => $vehicle->id, 'part' => Issue::PART_MOTORE, 'description' => 'Ha già la parte', 'status' => 'open', 'event_date' => '2026-01-01']);

        $response = $this->actingAs($user)->get(route('admin.issues.backfill-parts.index'));

        $response->assertOk();
        $response->assertSee('Senza parte');
        $response->assertDontSee('Ha già la parte');
    }

    public function test_member_cannot_access_backfill_page(): void
    {
        $user = $this->member();

        $response = $this->actingAs($user)->get(route('admin.issues.backfill-parts.index'));

        $response->assertForbidden();
    }

    public function test_suggest_flashes_suggestions_and_redirects_back(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $issue = Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Frena male', 'status' => 'open', 'event_date' => '2026-01-01']);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'classifications' => [['id' => $issue->id, 'part' => Issue::PART_FRENI]],
                    ])]],
                ],
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('admin.issues.backfill-parts.suggest'));

        $response->assertRedirect(route('admin.issues.backfill-parts.index'));
        $response->assertSessionHas('part_suggestions', [$issue->id => Issue::PART_FRENI]);
    }

    public function test_suggest_shows_error_on_classification_failure(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Boh', 'status' => 'open', 'event_date' => '2026-01-01']);

        Http::fake(['*/chat/completions' => Http::response([], 500)]);

        $response = $this->actingAs($user)->post(route('admin.issues.backfill-parts.suggest'));

        $response->assertRedirect(route('admin.issues.backfill-parts.index'));
        $response->assertSessionHas('error');
    }

    public function test_store_saves_confirmed_parts(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $issue1 = Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Frena male', 'status' => 'open', 'event_date' => '2026-01-01']);
        $issue2 = Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Foratura', 'status' => 'open', 'event_date' => '2026-01-01']);

        $response = $this->actingAs($user)->post(route('admin.issues.backfill-parts.store'), [
            'parts' => [
                $issue1->id => Issue::PART_FRENI,
                $issue2->id => Issue::PART_PNEUMATICI,
            ],
        ]);

        $response->assertRedirect(route('admin.issues.backfill-parts.index'));
        $this->assertSame(Issue::PART_FRENI, $issue1->fresh()->part);
        $this->assertSame(Issue::PART_PNEUMATICI, $issue2->fresh()->part);
    }

    public function test_store_leaves_unassigned_rows_untouched(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $issue = Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Boh', 'status' => 'open', 'event_date' => '2026-01-01']);

        $this->actingAs($user)->post(route('admin.issues.backfill-parts.store'), [
            'parts' => [$issue->id => ''],
        ]);

        $this->assertNull($issue->fresh()->part);
    }

    public function test_store_ignores_issue_id_from_another_group(): void
    {
        // Un id manomesso nel form non deve poter aggiornare il guasto di
        // un altro gruppo, anche se la parte inviata è valida.
        $user = $this->admin();
        $otherGroup = Group::create(['name' => 'Altro gruppo', 'invite_code' => Group::generateInviteCode()]);
        $otherVehicle = $this->vehicle($otherGroup);
        $foreignIssue = Issue::create(['vehicle_id' => $otherVehicle->id, 'description' => 'Non mio', 'status' => 'open', 'event_date' => '2026-01-01']);

        $this->actingAs($user)->post(route('admin.issues.backfill-parts.store'), [
            'parts' => [$foreignIssue->id => Issue::PART_MOTORE],
        ]);

        $this->assertNull($foreignIssue->fresh()->part);
    }

    public function test_store_rejects_part_outside_fixed_vocabulary(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $issue = Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Boh', 'status' => 'open', 'event_date' => '2026-01-01']);

        $response = $this->actingAs($user)->post(route('admin.issues.backfill-parts.store'), [
            'parts' => [$issue->id => 'Non esiste'],
        ]);

        $response->assertSessionHasErrors(['parts.'.$issue->id]);
        $this->assertNull($issue->fresh()->part);
    }
}
