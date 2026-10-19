<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Migrazione 2026_10_19_154504_add_marker_to_notifications_table: estrae il
 * marker (es. "#deadline-42") già accodato al testo del messaggio dalle
 * notifiche esistenti in una colonna propria, così alreadyNotified() può
 * smettere di usare un LIKE non indicizzabile su message (audit 2026-10-19).
 * Qui si richiama up() direttamente su righe seminate col formato "vecchio",
 * per verificare l'estrazione senza dipendere dall'ordine reale delle
 * migrazioni nella history di produzione.
 */
class NotificationMarkerBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_extracts_marker_and_strips_it_from_the_message(): void
    {
        // RefreshDatabase ha già applicato questa migrazione su un DB
        // vuoto (backfill no-op): per testare davvero l'estrazione si
        // riporta lo schema allo stato "prima" e si riesegue up() su righe
        // seminate col vecchio formato (marker accodato al messaggio).
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_dedup');
            $table->dropColumn('marker');
        });

        $user = User::factory()->create();

        $withMarkerId = DB::table('notifications')->insertGetId([
            'user_id' => $user->id,
            'type' => Notification::TYPE_DEADLINE,
            'title' => 'Scadenza in arrivo',
            'message' => 'Il veicolo 0001 ha una scadenza il 20/10/2026. #deadline-42',
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $escalationId = DB::table('notifications')->insertGetId([
            'user_id' => $user->id,
            'type' => Notification::TYPE_DEADLINE,
            'title' => 'Scadenza scaduta',
            'message' => 'Il veicolo 0002 ha una scadenza scaduta. #deadline-escalation-7-14',
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $withoutMarkerId = DB::table('notifications')->insertGetId([
            'user_id' => $user->id,
            'type' => Notification::TYPE_SYSTEM,
            'title' => 'Avviso di sistema',
            'message' => 'Nessun marker qui dentro, solo testo libero.',
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require base_path('database/migrations/2026_10_19_154504_add_marker_to_notifications_table.php');
        $migration->up();

        $withMarker = Notification::find($withMarkerId);
        $escalation = Notification::find($escalationId);
        $withoutMarker = Notification::find($withoutMarkerId);

        $this->assertSame('#deadline-42', $withMarker->marker);
        $this->assertSame('Il veicolo 0001 ha una scadenza il 20/10/2026.', $withMarker->message);

        $this->assertSame('#deadline-escalation-7-14', $escalation->marker);
        $this->assertSame('Il veicolo 0002 ha una scadenza scaduta.', $escalation->message);

        $this->assertNull($withoutMarker->marker);
        $this->assertSame('Nessun marker qui dentro, solo testo libero.', $withoutMarker->message);
    }
}
