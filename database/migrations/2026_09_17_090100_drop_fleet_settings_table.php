<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Le date di cambio gomme sono per-gruppo (vedi migrazione
     * add_tire_switch_dates_to_groups_table), non più un'unica riga
     * globale condivisa da tutta l'installazione.
     */
    public function up(): void
    {
        Schema::dropIfExists('fleet_settings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('fleet_settings', function ($table) {
            $table->id();
            $table->string('winter_switch_date', 5)->default('11-15');
            $table->string('summer_switch_date', 5)->default('04-15');
            $table->timestamps();
        });
    }
};
