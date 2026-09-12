<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estende l'enum type dei providers per includere 'Elettrauto' e 'Centro Revisioni'.
     */
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->enum('type', [
                'Meccanico',
                'Carrozziere',
                'Gommista',
                'Lavaggio',
                'Allestitore',
                'Vetri',
                'Elettrauto',
                'Centro Revisioni',
            ])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->enum('type', [
                'Meccanico',
                'Carrozziere',
                'Gommista',
                'Lavaggio',
                'Allestitore',
                'Vetri',
            ])->change();
        });
    }
};
