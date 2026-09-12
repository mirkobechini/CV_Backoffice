<?php

namespace Tests\Feature;

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
}
