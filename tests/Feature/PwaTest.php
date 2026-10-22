<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_file_exists_with_required_fields(): void
    {
        // File statico servito direttamente dal webserver in produzione (non
        // instradato da Laravel): il test TestCase non passa dal webserver,
        // quindi verifichiamo il contenuto sul filesystem invece di una
        // richiesta HTTP, che darebbe sempre 404 a prescindere dal file.
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertArrayHasKey('name', $manifest);
        $this->assertArrayHasKey('short_name', $manifest);
        $this->assertArrayHasKey('start_url', $manifest);
        $this->assertNotEmpty($manifest['icons']);
    }

    public function test_service_worker_file_exists_at_root_scope(): void
    {
        // Deve stare in /sw.js (root), non sotto /build/: lo scope di un
        // service worker è limitato alla cartella in cui risiede.
        $this->assertFileExists(public_path('sw.js'));
    }

    public function test_service_worker_does_not_cache_error_responses_and_prunes_old_caches(): void
    {
        // Audit 2026-10-22 (B5): metteva in cache anche risposte di errore
        // (es. 404 transitorio su un asset durante un deploy, reso così
        // permanentemente rotto) e non ripuliva mai le cache delle versioni
        // precedenti. Stesso motivo di PwaTest sopra per verificare il
        // contenuto sul filesystem invece che via richiesta HTTP.
        $sw = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString('response.ok', $sw);
        $this->assertStringContainsString('caches.delete', $sw);
    }

    public function test_dashboard_links_manifest_and_registers_service_worker(): void
    {
        $user = User::factory()->withRole('admin')->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('manifest.json', false);
        $response->assertSee('serviceWorker', false);
    }
}
