<?php

namespace Tests\Feature\Superadmin;

use App\Models\DiscountCode;
use App\Models\Group;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_assigns_license_with_discount_code(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $discountCode = DiscountCode::create(['code' => 'SCONTO20', 'percent' => 20, 'active' => true]);

        $response = $this->actingAs($superadmin)->post(route('superadmin.licenses.store', $group), [
            'tier' => License::TIER_PRO,
            'max_vehicles' => 15,
            'max_users' => 30,
            'features' => [License::FEATURE_STATISTICS, License::FEATURE_AI_BACKFILL],
            'list_price_eur' => 29,
            'discount_code' => 'sconto20',
            'starts_at' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('superadmin.dashboard'));

        $license = $group->fresh()->currentLicense();
        $this->assertNotNull($license);
        $this->assertSame(License::TIER_PRO, $license->tier);
        $this->assertSame($discountCode->id, $license->discount_code_id);
        $this->assertSame(20, $license->discount_percent);
        $this->assertEqualsWithDelta(23.2, (float) $license->final_price_eur, 0.001);
        $this->assertSame(1, $discountCode->fresh()->used_count);
    }

    public function test_assignment_rejects_invalid_discount_code(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);

        $response = $this->actingAs($superadmin)->post(route('superadmin.licenses.store', $group), [
            'tier' => License::TIER_FREE,
            'starts_at' => now()->toDateString(),
            'discount_code' => 'NONEXISTENT',
        ]);

        $response->assertSessionHasErrors('discount_code');
        $this->assertNull($group->fresh()->currentLicense());
    }

    public function test_renewing_creates_a_new_license_row_keeping_history(): void
    {
        $superadmin = User::factory()->create(['is_superadmin' => true]);
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);

        $this->actingAs($superadmin)->post(route('superadmin.licenses.store', $group), [
            'tier' => License::TIER_FREE,
            'starts_at' => now()->subYear()->toDateString(),
            'expires_at' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($superadmin)->post(route('superadmin.licenses.store', $group), [
            'tier' => License::TIER_PRO,
            'starts_at' => now()->toDateString(),
        ]);

        $this->assertSame(2, $group->licenses()->count());
        $this->assertSame(License::TIER_PRO, $group->fresh()->currentLicense()->tier);
    }
}
