<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pivot many-to-many: a differenza di un appuntamento veicolo (che
     * riguarda SEMPRE un solo veicolo), un appuntamento attrezzature può
     * coinvolgere più attrezzature insieme (es. collaudo di più estintori
     * dallo stesso fornitore nello stesso giorno).
     */
    public function up(): void
    {
        // dropIfExists difensivo: il nome vincolo di default generato da
        // Laravel per la prima FK ("..._equipment_maintenance_record_id_foreign")
        // supera i 64 caratteri ammessi da MySQL per un identificatore,
        // facendo fallire questa migrazione in produzione DOPO che la CREATE
        // TABLE (comando DDL, autocommit in MySQL anche dentro una
        // migrazione) era già stata eseguita — un nuovo tentativo ripartirebbe
        // da una tabella già esistente ma priva del vincolo. Nomi vincolo
        // espliciti e brevi qui sotto evitano il problema anche per il
        // futuro (es. un rollback/replay della migrazione).
        Schema::dropIfExists('equipment_maintenance_record_equipment');

        Schema::create('equipment_maintenance_record_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_maintenance_record_id')
                ->constrained(indexName: 'emr_equip_pivot_record_fk')
                ->cascadeOnDelete();
            $table->foreignId('equipment_id')
                ->constrained(indexName: 'emr_equip_pivot_equipment_fk')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['equipment_maintenance_record_id', 'equipment_id'], 'emre_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_maintenance_record_equipment');
    }
};
