<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estende l'enum type dei providers per includere 'Vetri'.
     */
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->enum('type', ['Meccanico', 'Carrozziere', 'Gommista', 'Lavaggio', 'Allestitore', 'Vetri'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->enum('type', ['Meccanico', 'Carrozziere', 'Gommista', 'Lavaggio', 'Allestitore'])->change();
        });
    }
};
