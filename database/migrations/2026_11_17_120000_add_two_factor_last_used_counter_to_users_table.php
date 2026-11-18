<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contatore del passo temporale (time-step, `time() / 30`, non un
     * timestamp unix) dell'ultimo codice TOTP accettato — permette di
     * rifiutare un codice già usato finché è ancora nella finestra di
     * validità (`Google2FA::verifyKeyNewer()`), altrimenti un codice
     * intercettato resterebbe riutilizzabile per ~30-90 secondi dopo il
     * primo uso legittimo (audit sicurezza SL3).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('two_factor_last_used_counter')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_last_used_counter');
        });
    }
};
