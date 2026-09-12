<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Le impostazioni di notifica passano da "globali per tutta
     * l'applicazione" a "personali per ogni account". Copiamo i valori
     * già configurati (se presenti) su ogni utente esistente, così nessuno
     * perde la configurazione attuale; da qui in poi ognuno gestisce le
     * proprie impostazioni in autonomia.
     */
    public function up(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $globalSettings = DB::table('notification_settings')->whereNull('user_id')->get(['key', 'value']);

        if ($globalSettings->isNotEmpty()) {
            $userIds = DB::table('users')->pluck('id');

            foreach ($userIds as $userId) {
                foreach ($globalSettings as $setting) {
                    DB::table('notification_settings')->insert([
                        'user_id' => $userId,
                        'key' => $setting->key,
                        'value' => $setting->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('notification_settings')->whereNull('user_id')->delete();
        }

        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->unique(['user_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'key']);
        });

        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('notification_settings', function (Blueprint $table) {
            $table->unique('key');
        });
    }
};
