<?php

namespace Tests\Unit;

use App\Models\FleetSetting;
use App\Models\Tire;
use App\Services\TireSeasonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TireSeasonServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TireSeasonService
    {
        return new TireSeasonService();
    }

    public function test_default_switch_dates_are_november_15_and_april_15(): void
    {
        $settings = FleetSetting::current();

        $this->assertSame('11-15', $settings->winter_switch_date);
        $this->assertSame('04-15', $settings->summer_switch_date);
    }

    public function test_expected_season_is_winter_right_after_november_switch(): void
    {
        $service = $this->service();

        $this->assertSame(Tire::SEASON_WINTER, $service->expectedSeason(Carbon::parse('2026-11-15')));
        $this->assertSame(Tire::SEASON_WINTER, $service->expectedSeason(Carbon::parse('2026-12-25')));
        $this->assertSame(Tire::SEASON_WINTER, $service->expectedSeason(Carbon::parse('2027-01-10')));
    }

    public function test_expected_season_is_winter_right_before_april_switch(): void
    {
        $service = $this->service();

        $this->assertSame(Tire::SEASON_WINTER, $service->expectedSeason(Carbon::parse('2026-04-14')));
    }

    public function test_expected_season_is_summer_right_after_april_switch(): void
    {
        $service = $this->service();

        $this->assertSame(Tire::SEASON_SUMMER, $service->expectedSeason(Carbon::parse('2026-04-15')));
        $this->assertSame(Tire::SEASON_SUMMER, $service->expectedSeason(Carbon::parse('2026-07-01')));
    }

    public function test_expected_season_is_summer_right_before_november_switch(): void
    {
        $service = $this->service();

        $this->assertSame(Tire::SEASON_SUMMER, $service->expectedSeason(Carbon::parse('2026-11-14')));
    }

    public function test_expected_season_respects_custom_switch_dates(): void
    {
        FleetSetting::current()->update([
            'winter_switch_date' => '11-01',
            'summer_switch_date' => '04-01',
        ]);

        $service = $this->service();

        $this->assertSame(Tire::SEASON_SUMMER, $service->expectedSeason(Carbon::parse('2026-04-01')));
        $this->assertSame(Tire::SEASON_WINTER, $service->expectedSeason(Carbon::parse('2026-03-31')));
    }
}
