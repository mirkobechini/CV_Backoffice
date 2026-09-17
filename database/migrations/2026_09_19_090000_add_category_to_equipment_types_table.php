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
     * "category" determina quali campi extra (agente estinguente, peso,
     * tipo sedia, kg massimo, ecc.) mostrare per gli elementi di
     * attrezzatura di questo tipo — necessario perché EquipmentType è
     * liberamente creabile/rinominabile dall'utente, quindi non si può
     * dedurre la categoria dal nome.
     */
    public function up(): void
    {
        Schema::table('equipment_types', function (Blueprint $table) {
            $table->string('category')->default('other')->after('name');
            // Solo per category=fire_extinguisher: intervallo per il
            // collaudo idraulico (ciclo separato dalla revisione) e dopo
            // quante revisioni l'estintore va sostituito.
            $table->unsignedInteger('collaudo_interval_months')->nullable()->after('regular_inspection_months');
            $table->unsignedInteger('max_revisions_before_exchange')->nullable()->after('collaudo_interval_months');
        });

        // Assegna la categoria ai 3 tipi già seedati, riconoscibili per nome.
        DB::table('equipment_types')->where('name', 'Estintore')->update(['category' => 'fire_extinguisher']);
        DB::table('equipment_types')->where('name', 'Barella')->update(['category' => 'stretcher']);
        DB::table('equipment_types')->where('name', 'Seggiola')->update(['category' => 'chair']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment_types', function (Blueprint $table) {
            $table->dropColumn(['category', 'collaudo_interval_months', 'max_revisions_before_exchange']);
        });
    }
};
