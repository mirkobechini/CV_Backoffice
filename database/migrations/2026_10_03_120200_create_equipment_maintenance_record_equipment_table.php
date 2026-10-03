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
        Schema::create('equipment_maintenance_record_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_maintenance_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
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
