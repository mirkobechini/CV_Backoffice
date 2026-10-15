<?php

namespace Tests\Feature\License;

use App\Models\Group;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseFeatureGatingTest extends TestCase
{
    use RefreshDatabase;

    private function assignLicense(Group $group, array $features): License
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        return $group->licenses()->create([
            'tier' => License::TIER_FREE,
            'features' => $features,
            'starts_at' => now(),
            'created_by' => $superadmin->id,
        ]);
    }

    public function test_statistics_blocked_when_license_lacks_feature(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);
        $this->assignLicense($group, []);

        $this->actingAs($capo)->get(route('admin.statistics.index'))->assertForbidden();
    }

    public function test_statistics_allowed_when_license_has_feature(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);
        $this->assignLicense($group, [License::FEATURE_STATISTICS]);

        $this->actingAs($capo)->get(route('admin.statistics.index'))->assertOk();
    }

    public function test_statistics_allowed_without_any_license(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);

        $this->actingAs($capo)->get(route('admin.statistics.index'))->assertOk();
    }

    public function test_ai_backfill_blocked_when_license_lacks_feature(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);
        $this->assignLicense($group, []);

        $this->actingAs($capo)->get(route('admin.issues.backfill-parts.index'))->assertForbidden();
    }

    public function test_ai_backfill_allowed_when_license_has_feature(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);
        $this->assignLicense($group, [License::FEATURE_AI_BACKFILL]);

        $this->actingAs($capo)->get(route('admin.issues.backfill-parts.index'))->assertOk();
    }

    public function test_expired_license_banner_is_shown(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);
        $group->licenses()->create([
            'tier' => License::TIER_FREE,
            'features' => [],
            'starts_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
            'created_by' => User::factory()->create(['is_superadmin' => true])->id,
        ]);

        $this->actingAs($capo)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('licenza del tuo gruppo è scaduta');
    }

    public function test_no_banner_without_expired_license(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);

        $this->actingAs($capo)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('licenza del tuo gruppo è scaduta');
    }
}
