<?php

namespace Tests\Feature;

use App\Models\FleetSetting;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function capoWithGroup(): array
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);

        return [$capo, $group];
    }

    public function test_settings_page_is_accessible(): void
    {
        [$capo] = $this->capoWithGroup();

        $response = $this->actingAs($capo)->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertSee('Impostazioni');
    }

    public function test_settings_page_links_to_account_and_groups(): void
    {
        // Le impostazioni del gruppo si gestiscono solo dalla pagina del
        // gruppo: qui restano solo il rimando all'account e il backup.
        [$capo] = $this->capoWithGroup();

        $response = $this->actingAs($capo)->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertSee(route('profile.edit'), false);
        $response->assertSee(route('admin.groups.index'), false);
    }

    public function test_backup_creates_file(): void
    {
        Storage::fake('local');
        [$capo] = $this->capoWithGroup();

        $response = $this->actingAs($capo)->post(route('admin.settings.backup'));

        $response->assertRedirect();
        $files = Storage::disk('local')->files('backups');
        $this->assertNotEmpty($files);
    }

    public function test_member_cannot_trigger_backup(): void
    {
        // Prima non c'era alcun controllo: qualunque utente autenticato,
        // anche un membro base, poteva lanciare il backup direttamente
        // sulla route.
        Storage::fake('local');
        [, $group] = $this->capoWithGroup();
        $member = User::factory()->create();
        $group->addUser($member, Group::ROLE_MEMBER);

        $response = $this->actingAs($member)->post(route('admin.settings.backup'));

        $response->assertForbidden();
        $this->assertEmpty(Storage::disk('local')->files('backups'));
    }

    public function test_member_does_not_see_backup_section(): void
    {
        [, $group] = $this->capoWithGroup();
        $member = User::factory()->create();
        $group->addUser($member, Group::ROLE_MEMBER);

        $response = $this->actingAs($member)->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertDontSee('Crea backup');
    }

    public function test_capo_can_update_tire_season_switch_dates(): void
    {
        [$capo] = $this->capoWithGroup();

        $response = $this->actingAs($capo)->patch(route('admin.settings.tire-season.update'), [
            'winter_switch_month' => 11,
            'winter_switch_day' => 1,
            'summer_switch_month' => 4,
            'summer_switch_day' => 1,
        ]);

        $response->assertRedirect();
        $this->assertSame('11-01', FleetSetting::current()->winter_switch_date);
        $this->assertSame('04-01', FleetSetting::current()->summer_switch_date);
    }

    public function test_member_cannot_update_tire_season_switch_dates(): void
    {
        [, $group] = $this->capoWithGroup();
        $member = User::factory()->create();
        $group->addUser($member, Group::ROLE_MEMBER);

        $response = $this->actingAs($member)->patch(route('admin.settings.tire-season.update'), [
            'winter_switch_month' => 11,
            'winter_switch_day' => 1,
            'summer_switch_month' => 4,
            'summer_switch_day' => 1,
        ]);

        $response->assertForbidden();
    }

    public function test_tire_season_switch_dates_reject_invalid_day_for_month(): void
    {
        [$capo] = $this->capoWithGroup();

        $response = $this->actingAs($capo)->patch(route('admin.settings.tire-season.update'), [
            'winter_switch_month' => 2,
            'winter_switch_day' => 30,
            'summer_switch_month' => 4,
            'summer_switch_day' => 15,
        ]);

        $response->assertSessionHasErrors(['winter_switch_day']);
    }
}
