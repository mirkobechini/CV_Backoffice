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
        Storage::fake('public');
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $response = $this->actingAs($superadmin)->post(route('admin.settings.backup'));

        $response->assertRedirect();
        $files = Storage::disk('public')->files('backups');
        $this->assertNotEmpty($files);
    }

    public function test_capo_cannot_trigger_backup(): void
    {
        // Falla di sicurezza corretta nell'audit 2026-10-22: il backup
        // creato da app:backup-database è un unico dump dell'INTERO
        // database multi-tenant, non del solo gruppo di chi lo richiede —
        // prima bastava essere capo/sottocapo di UNA QUALSIASI associazione
        // per vedere ed eseguire i backup (quindi i dati) di tutte le
        // altre. Ora è riservato al superadmin.
        Storage::fake('public');
        [$capo] = $this->capoWithGroup();

        $response = $this->actingAs($capo)->post(route('admin.settings.backup'));

        $response->assertForbidden();
        $this->assertEmpty(Storage::disk('public')->files('backups'));
    }

    public function test_member_cannot_trigger_backup(): void
    {
        // Prima non c'era alcun controllo: qualunque utente autenticato,
        // anche un membro base, poteva lanciare il backup direttamente
        // sulla route.
        Storage::fake('public');
        [, $group] = $this->capoWithGroup();
        $member = User::factory()->create();
        $group->addUser($member, Group::ROLE_MEMBER);

        $response = $this->actingAs($member)->post(route('admin.settings.backup'));

        $response->assertForbidden();
        $this->assertEmpty(Storage::disk('public')->files('backups'));
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

    public function test_capo_does_not_see_backup_section(): void
    {
        // Stessa falla di test_capo_cannot_trigger_backup: la sezione
        // backup ora non è visibile nemmeno a chi è capo del proprio
        // gruppo, solo al superadmin.
        [$capo] = $this->capoWithGroup();

        $response = $this->actingAs($capo)->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertDontSee('Crea backup');
    }
}
