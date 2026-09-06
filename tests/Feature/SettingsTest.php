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

    public function test_capo_can_update_group_name(): void
    {
        [$capo, $group] = $this->capoWithGroup();

        $response = $this->actingAs($capo)->patch(route('admin.settings.group'), [
            'name' => 'Nuova Associazione',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'name' => 'Nuova Associazione',
        ]);
    }

    public function test_member_cannot_update_group_name(): void
    {
        [$capo, $group] = $this->capoWithGroup();
        $member = User::factory()->create();
        $group->addUser($member, Group::ROLE_MEMBER);

        $response = $this->actingAs($member)->patch(route('admin.settings.group'), [
            'name' => 'Nuova Associazione',
        ]);

        $response->assertForbidden();
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
