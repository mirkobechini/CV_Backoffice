<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estende l'enum type per includere Tagliando e Cinghia Distribuzione,
     * e rende due_date nullable (le scadenze km-based non hanno una data).
     */
    public function up(): void
    {
        // Estende l'enum type (portabile: Laravel traduce per MySQL/SQLite)
        Schema::table('deadlines', function (Blueprint $table) {
            $table->enum('type', ['Assicurazione', 'Revisione Ministeriale', 'Revisione Impianto Ossigeno', 'Tagliando', 'Cinghia Distribuzione'])->change();
        });

        // Rende due_date nullable
        Schema::table('deadlines', function (Blueprint $table) {
            $table->date('due_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Riporta due_date a NOT NULL (solo se non ci sono valori null)
        Schema::table('deadlines', function (Blueprint $table) {
            $table->date('due_date')->nullable(false)->change();
        });

        // Riporta l'enum type originale
        Schema::table('deadlines', function (Blueprint $table) {
            $table->enum('type', ['Assicurazione', 'Revisione Ministeriale', 'Revisione Impianto Ossigeno'])->change();
        });
    }
};
