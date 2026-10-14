<?php

namespace Tests\Unit;

use App\Models\License;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_expired_is_false_without_expiry_date(): void
    {
        $license = new License(['expires_at' => null]);

        $this->assertFalse($license->isExpired());
    }

    public function test_is_expired_is_true_when_expiry_date_is_past(): void
    {
        $license = new License(['expires_at' => now()->subDay()]);

        $this->assertTrue($license->isExpired());
    }

    public function test_is_expired_is_false_when_expiry_date_is_future(): void
    {
        $license = new License(['expires_at' => now()->addDay()]);

        $this->assertFalse($license->isExpired());
    }

    public function test_has_feature_checks_features_array(): void
    {
        $license = new License(['features' => [License::FEATURE_STATISTICS]]);

        $this->assertTrue($license->hasFeature(License::FEATURE_STATISTICS));
        $this->assertFalse($license->hasFeature(License::FEATURE_AI_BACKFILL));
    }

    public function test_has_feature_returns_false_when_expired(): void
    {
        $license = new License(['features' => [License::FEATURE_STATISTICS], 'expires_at' => now()->subDay()]);

        $this->assertFalse($license->hasFeature(License::FEATURE_STATISTICS));
    }

    public function test_has_vehicle_capacity_null_limit_is_unlimited(): void
    {
        $license = new License(['max_vehicles' => null]);

        $this->assertTrue($license->hasVehicleCapacity(1000));
    }

    public function test_has_vehicle_capacity_respects_limit(): void
    {
        $license = new License(['max_vehicles' => 3]);

        $this->assertTrue($license->hasVehicleCapacity(2));
        $this->assertFalse($license->hasVehicleCapacity(3));
    }

    public function test_has_user_capacity_respects_limit(): void
    {
        $license = new License(['max_users' => 5]);

        $this->assertTrue($license->hasUserCapacity(4));
        $this->assertFalse($license->hasUserCapacity(5));
    }

    public function test_compute_final_price_applies_discount(): void
    {
        $this->assertEqualsWithDelta(23.2, License::computeFinalPrice(29, 20), 0.001);
    }

    public function test_compute_final_price_without_discount_equals_list_price(): void
    {
        $this->assertSame(29.0, License::computeFinalPrice(29, null));
    }

    public function test_compute_final_price_is_null_without_list_price(): void
    {
        $this->assertNull(License::computeFinalPrice(null, 20));
    }
}
