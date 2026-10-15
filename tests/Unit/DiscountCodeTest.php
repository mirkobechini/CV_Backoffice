<?php

namespace Tests\Unit;

use App\Models\DiscountCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_redeem_succeeds_and_increments_used_count(): void
    {
        $code = DiscountCode::create(['code' => 'TEST1', 'percent' => 10, 'active' => true]);

        $this->assertTrue($code->redeem());
        $this->assertSame(1, $code->fresh()->used_count);
    }

    public function test_redeem_fails_when_inactive(): void
    {
        $code = DiscountCode::create(['code' => 'TEST2', 'percent' => 10, 'active' => false]);

        $this->assertFalse($code->redeem());
        $this->assertSame(0, $code->fresh()->used_count);
    }

    public function test_redeem_fails_when_expired(): void
    {
        $code = DiscountCode::create(['code' => 'TEST3', 'percent' => 10, 'active' => true, 'expires_at' => now()->subDay()]);

        $this->assertFalse($code->redeem());
    }

    public function test_redeem_fails_when_max_uses_reached(): void
    {
        $code = DiscountCode::create(['code' => 'TEST4', 'percent' => 10, 'active' => true, 'max_uses' => 1]);
        $code->forceFill(['used_count' => 1])->save();

        $this->assertFalse($code->redeem());
    }

    public function test_redeem_succeeds_when_under_max_uses(): void
    {
        $code = DiscountCode::create(['code' => 'TEST5', 'percent' => 10, 'active' => true, 'max_uses' => 2]);
        $code->forceFill(['used_count' => 1])->save();

        $this->assertTrue($code->redeem());
        $this->assertSame(2, $code->fresh()->used_count);
    }
}
