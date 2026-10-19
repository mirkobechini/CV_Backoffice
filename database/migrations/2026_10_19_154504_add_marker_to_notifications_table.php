<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('marker', 100)->nullable()->after('message');
            $table->index(['user_id', 'type', 'marker'], 'idx_notifications_dedup');
        });

        // Backfill: GenerateNotifications::dispatchEvent() appendeva sempre
        // il marker in coda al messaggio ("{$message} {$marker}"), usato poi
        // da alreadyNotified() per un confronto "LIKE %marker%" non
        // indicizzabile (audit 2026-10-19: ora si usa questa colonna con
        // indice invece). Senza backfill, ogni notifica già inviata in
        // passato risulterebbe "mai notificata" al prossimo giro e verrebbe
        // rimandata — il marker è sempre l'ultimo token del messaggio,
        // riconoscibile da "#" iniziale e nessuno spazio al suo interno
        // (vedi i vari notify*() nello stesso file).
        DB::table('notifications')
            ->whereNotNull('message')
            ->where('message', 'like', '%#%')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    if (! preg_match('/\s(#\S+)$/', (string) $row->message, $m)) {
                        continue;
                    }

                    DB::table('notifications')->where('id', $row->id)->update([
                        'marker' => $m[1],
                        'message' => substr($row->message, 0, -strlen($m[1]) - 1),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_dedup');
            $table->dropColumn('marker');
        });
    }
};
