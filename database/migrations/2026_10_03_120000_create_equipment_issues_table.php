<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('equipment_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained()->cascadeOnDelete();
            // Collega il guasto all'appuntamento che lo risolve. FK diretta
            // invece del pattern polimorfico MaintenanceRecordItem dei
            // veicoli: qui non serve collegare anche gomme/scadenze, solo
            // guasti, quindi una FK semplice basta ed è meno codice da
            // mantenere. Aggiunta in una migrazione separata dopo la
            // creazione di equipment_maintenance_records.
            $table->text('description');
            $table->enum('status', ['open', 'in_progress', 'closed'])->default('open');
            $table->date('event_date')->default(DB::raw('(CURRENT_DATE)'));
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_issues');
    }
};
