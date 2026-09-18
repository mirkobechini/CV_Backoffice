<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Token segreto e non indovinabile per la pagina pubblica di stato
     * flotta del gruppo: null = pagina disattivata. Chi ha il link vede
     * lo stato senza autenticarsi; non è un codice mostrato/condiviso
     * ampiamente come invite_code, va trattato come un segreto.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->string('public_status_token', 64)->nullable()->unique()->after('invite_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('public_status_token');
        });
    }
};
