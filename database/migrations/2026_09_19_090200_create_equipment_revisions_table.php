<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Storico di ogni revisione/collaudo effettuato su un'attrezzatura:
     * "revision" è il controllo periodico ordinario, "collaudo" è il
     * retest idraulico degli estintori (ciclo separato). Il conteggio
     * delle revisioni "revision" determina quando un estintore ha
     * raggiunto max_revisions_before_exchange (vedi EquipmentType).
     */
    public function up(): void
    {
        Schema::create('equipment_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->onDelete('cascade');
            $table->enum('kind', ['revision', 'collaudo'])->default('revision');
            $table->date('performed_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_revisions');
    }
};
