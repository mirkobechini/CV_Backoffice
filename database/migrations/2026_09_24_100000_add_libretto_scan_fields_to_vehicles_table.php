<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dati aggiuntivi ricavabili dal libretto di circolazione (scansione
     * tramite LLM vision, vedi VehicleScanService), tutti facoltativi:
     * un veicolo resta creabile a mano senza libretto o con dati incompleti.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('vin')->nullable()->after('has_timing_belt');
            $table->string('color')->nullable()->after('vin');
            $table->unsignedTinyInteger('seats')->nullable()->after('color');
            $table->string('environmental_class')->nullable()->after('seats');
            $table->unsignedInteger('max_mass_kg')->nullable()->after('environmental_class');
            $table->unsignedInteger('engine_displacement_cc')->nullable()->after('max_mass_kg');
            $table->unsignedInteger('engine_power_kw')->nullable()->after('engine_displacement_cc');
            $table->string('vehicle_category')->nullable()->after('engine_power_kw');
            $table->string('allowed_tire_size')->nullable()->after('vehicle_category');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'vin',
                'color',
                'seats',
                'environmental_class',
                'max_mass_kg',
                'engine_displacement_cc',
                'engine_power_kw',
                'vehicle_category',
                'allowed_tire_size',
            ]);
        });
    }
};
