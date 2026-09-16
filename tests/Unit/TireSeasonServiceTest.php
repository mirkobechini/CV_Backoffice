<?php

namespace Tests\Unit;

use App\Models\Group;
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

    private function group(array $overrides = []): Group
    {
        // refresh(): Group::create() con colonne non passate esplicitamente
        // non riflette i default DB (winter/summer_switch_date) sull'istanza
        // in memoria finché non viene ricaricata dal database.
        return Group::create(array_merge([
            'name' => 'Gruppo Test',
            'invite_code' => 'AAAA1111',
        ], $overrides))->refresh();
    }

    public function test_new_group_defaults_to_november_15_and_april_15(): void
    {
        $group = $this->group();

        $this->assertSame('11-15', $group->winter_switch_date);
        $this->assertSame('04-15', $group->summer_switch_date);
    }

    public function test_expected_season_is_winter_right_after_november_switch(): void
    {
        $service = $this->service();
        $group = $this->group();

        $this->assertSame(Tire::SEASON_WINTER, $service->expectedSeasonForGroup($group, Carbon::parse('2026-11-15')));
        $this->assertSame(Tire::SEASON_WINTER, $service->expectedSeasonForGroup($group, Carbon::parse('2026-12-25')));
        $this->assertSame(Tire::SEASON_WINTER, $service->expectedSeasonForGroup($group, Carbon::parse('2027-01-10')));
    }

    public function test_expected_season_is_winter_right_before_april_switch(): void
    {
        $service = $this->service();
        $group = $this->group();

        $this->assertSame(Tire::SEASON_WINTER, $service->expectedSeasonForGroup($group, Carbon::parse('2026-04-14')));
    }

    public function test_expected_season_is_summer_right_after_april_switch(): void
    {
        $service = $this->service();
        $group = $this->group();

        $this->assertSame(Tire::SEASON_SUMMER, $service->expectedSeasonForGroup($group, Carbon::parse('2026-04-15')));
        $this->assertSame(Tire::SEASON_SUMMER, $service->expectedSeasonForGroup($group, Carbon::parse('2026-07-01')));
    }

    public function test_expected_season_is_summer_right_before_november_switch(): void
    {
        $service = $this->service();
        $group = $this->group();

        $this->assertSame(Tire::SEASON_SUMMER, $service->expectedSeasonForGroup($group, Carbon::parse('2026-11-14')));
    }

    public function test_expected_season_respects_custom_switch_dates(): void
    {
        $service = $this->service();
        $group = $this->group([
            'winter_switch_date' => '11-01',
            'summer_switch_date' => '04-01',
        ]);

        $this->assertSame(Tire::SEASON_SUMMER, $service->expectedSeasonForGroup($group, Carbon::parse('2026-04-01')));
        $this->assertSame(Tire::SEASON_WINTER, $service->expectedSeasonForGroup($group, Carbon::parse('2026-03-31')));
    }

    public function test_expected_season_falls_back_to_defaults_without_a_group(): void
    {
        $service = $this->service();

        $this->assertSame(Tire::SEASON_WINTER, $service->expectedSeasonForGroup(null, Carbon::parse('2026-12-01')));
        $this->assertSame(Tire::SEASON_SUMMER, $service->expectedSeasonForGroup(null, Carbon::parse('2026-07-01')));
    }

    public function test_two_groups_can_have_independent_switch_dates(): void
    {
        $service = $this->service();
        $groupA = $this->group(['name' => 'A', 'invite_code' => 'AAAA1111', 'winter_switch_date' => '11-01', 'summer_switch_date' => '04-01']);
        $groupB = $this->group(['name' => 'B', 'invite_code' => 'BBBB2222', 'winter_switch_date' => '12-01', 'summer_switch_date' => '05-01']);

        $date = Carbon::parse('2026-11-20');

        $this->assertSame(Tire::SEASON_WINTER, $service->expectedSeasonForGroup($groupA, $date));
        $this->assertSame(Tire::SEASON_SUMMER, $service->expectedSeasonForGroup($groupB, $date));
    }
}
