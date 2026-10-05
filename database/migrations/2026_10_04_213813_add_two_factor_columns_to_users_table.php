<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Tutti cifrati (cast 'encrypted'/'encrypted:array' sul model):
            // il segreto TOTP e i codici di recupero sono equivalenti a
            // password, non vanno leggibili in chiaro da un dump del DB.
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            // Null finché l'utente non conferma il primo codice: un
            // segreto generato ma non confermato non deve abilitare il
            // 2FA (vedi User::hasTwoFactorEnabled()).
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
