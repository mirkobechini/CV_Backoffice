<?php

namespace Tests\Unit;

use App\Models\Group;
use App\Models\Tire;
use App\Models\Vehicle;
use App\Services\TireSeasonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TireSeasonServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TireSeasonService
    {
        return new TireSeasonService;
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

    public function test_next_switch_is_winter_when_summer_tires_currently_expected(): void
    {
        $service = $this->service();
        $group = $this->group();

        $next = $service->nextSwitch($group, Carbon::parse('2026-10-15'));

        $this->assertSame(Tire::SEASON_WINTER, $next['season']);
        $this->assertTrue($next['date']->isSameDay(Carbon::parse('2026-11-15')));
    }

    public function test_next_switch_is_summer_when_winter_tires_currently_expected(): void
    {
        $service = $this->service();
        $group = $this->group();

        $next = $service->nextSwitch($group, Carbon::parse('2026-12-01'));

        $this->assertSame(Tire::SEASON_SUMMER, $next['season']);
        $this->assertTrue($next['date']->isSameDay(Carbon::parse('2027-04-15')));
    }

    public function test_next_switch_rolls_over_to_next_year_right_after_the_date_passed(): void
    {
        $service = $this->service();
        $group = $this->group();

        $next = $service->nextSwitch($group, Carbon::parse('2026-11-16'));

        $this->assertSame(Tire::SEASON_SUMMER, $next['season']);
        $this->assertTrue($next['date']->isSameDay(Carbon::parse('2027-04-15')));
    }

    public function test_expected_season_with_warning_matches_strict_season_outside_the_window(): void
    {
        $service = $this->service();
        $group = $this->group();

        // Cambio invernale 15/11: a settembre siamo ben fuori dai 30 giorni
        // di preavviso, nessuna anticipazione.
        $this->assertSame(Tire::SEASON_SUMMER, $service->expectedSeasonWithWarning($group, Carbon::parse('2026-09-01')));
    }

    public function test_expected_season_with_warning_anticipates_the_switch_inside_the_window(): void
    {
        $service = $this->service();
        $group = $this->group();

        // Cambio invernale 15/11: il 20/10 siamo dentro i 30 giorni di
        // preavviso, la stagione attesa diventa già invernale.
        $this->assertSame(Tire::SEASON_WINTER, $service->expectedSeasonWithWarning($group, Carbon::parse('2026-10-20')));
    }

    public function test_expected_season_with_warning_respects_custom_warning_days(): void
    {
        $service = $this->service();
        $group = $this->group();

        $this->assertSame(Tire::SEASON_SUMMER, $service->expectedSeasonWithWarning($group, Carbon::parse('2026-10-20'), 5));
        $this->assertSame(Tire::SEASON_WINTER, $service->expectedSeasonWithWarning($group, Carbon::parse('2026-10-20'), 60));
    }

    public function test_expected_season_with_warning_matches_strict_season_once_the_switch_has_passed(): void
    {
        $service = $this->service();
        $group = $this->group();

        $this->assertSame(Tire::SEASON_WINTER, $service->expectedSeasonWithWarning($group, Carbon::parse('2026-11-20')));
    }

    public function test_is_compliant_uses_expected_season_override_when_given(): void
    {
        $service = $this->service();
        $group = $this->group();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $group->id,
        ]);
        Tire::create([
            'vehicle_id' => $vehicle->id,
            'season' => Tire::SEASON_SUMMER,
            'position' => Tire::POSITION_FRONT_LEFT,
            'status' => Tire::STATUS_MOUNTED,
        ]);

        // Gomme estive montate: senza override è conforme (stagione estiva
        // attesa di default), con override invernale non lo è più.
        $this->assertTrue($service->isCompliant($vehicle));
        $this->assertFalse($service->isCompliant($vehicle, Tire::SEASON_WINTER));
    }
}
