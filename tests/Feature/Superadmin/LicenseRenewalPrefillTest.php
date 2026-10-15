<?php

namespace Tests\Feature\Superadmin;

use App\Models\Group;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseRenewalPrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_renewal_form_prefills_current_license_values(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->licenses()->create([
            'tier' => License::TIER_PRO,
            'max_vehicles' => 15,
            'max_users' => 30,
            'features' => [License::FEATURE_STATISTICS, License::FEATURE_AI_BACKFILL],
            'list_price_eur' => 29,
            'starts_at' => now()->subMonth(),
            'created_by' => $superadmin->id,
        ]);

        $response = $this->actingAs($superadmin)->get(route('superadmin.licenses.create', $group));

        $response->assertOk();
        $response->assertSee('value="15"', false);
        $response->assertSee('value="30"', false);
        $response->assertSee('value="29.00"', false);
        // Il tier Pro deve essere preselezionato, non il primo (Free).
        $response->assertSeeInOrder(['<option value="pro" selected', '</select>'], false);
    }

    public function test_first_assignment_form_has_empty_fields(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);

        $response = $this->actingAs($superadmin)->get(route('superadmin.licenses.create', $group));

        $response->assertOk();
        $response->assertDontSee('selected', false);
    }
}
