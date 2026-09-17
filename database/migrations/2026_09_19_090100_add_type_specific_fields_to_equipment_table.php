<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Campi condivisi da tutte le categorie (brand/model/note/data di
     * fabbricazione/numero identificativo) più i campi specifici di
     * estintori (agente, peso, collaudo) e sedie/barelle (tipo sedia,
     * kg massimo) — vedi EquipmentType.category per quali si mostrano.
     */
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('name');
            $table->string('model')->nullable()->after('brand');
            $table->string('identification_number')->nullable()->after('serial_number');
            $table->date('fabrication_date')->nullable()->after('identification_number');

            // Estintori
            $table->string('extinguisher_agent')->nullable()->after('expiration_date');
            $table->decimal('weight_kg', 5, 2)->nullable()->after('extinguisher_agent');
            $table->date('collaudo_date')->nullable()->after('weight_kg');
            $table->date('next_collaudo_date')->nullable()->after('collaudo_date');

            // Sedie/barelle
            $table->string('chair_type')->nullable()->after('next_collaudo_date');
            $table->decimal('max_weight_kg', 6, 2)->nullable()->after('chair_type');

            $table->text('notes')->nullable()->after('max_weight_kg');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn([
                'brand',
                'model',
                'identification_number',
                'fabrication_date',
                'extinguisher_agent',
                'weight_kg',
                'collaudo_date',
                'next_collaudo_date',
                'chair_type',
                'max_weight_kg',
                'notes',
            ]);
        });
    }
};
