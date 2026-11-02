<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per i veicoli "ibrido" (es. benzina/metano), quali carburanti
     * specifici ammette: Vehicle.fuel_type da solo non lo dice (issue-585
     * follow-up). Non serve per gli altri veicoli, dove l'unico carburante
     * ammesso è già Vehicle.fuel_type stesso.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->json('allowed_fuel_types')->nullable()->after('fuel_type');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('allowed_fuel_types');
        });
    }
};
