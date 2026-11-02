<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipo carburante del singolo rifornimento, indipendente da
     * Vehicle.fuel_type: un veicolo "ibrido" benzina/metano fa rifornimenti
     * ora dell'uno ora dell'altro, e le statistiche devono poterli
     * distinguere (issue-585). Backfill dai veicoli con un'alimentazione
     * univoca: per quelli "ibrido" resta null (nessun modo di dedurre quale
     * carburante sia stato messo in ogni singolo pieno passato).
     */
    public function up(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->enum('fuel_type', ['benzina', 'diesel', 'elettrico', 'metano'])->nullable()->after('vehicle_id');
        });

        // Un UPDATE con JOIN non è portabile (SQLite non supporta un
        // riferimento a una colonna di un'altra tabella nel SET), quindi un
        // giro separato per valore invece di un singolo UPDATE...JOIN.
        foreach (['benzina', 'diesel', 'elettrico'] as $fuelType) {
            DB::table('fuel_logs')
                ->whereIn('vehicle_id', DB::table('vehicles')->where('fuel_type', $fuelType)->pluck('id'))
                ->update(['fuel_type' => $fuelType]);
        }
    }

    public function down(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->dropColumn('fuel_type');
        });
    }
};
