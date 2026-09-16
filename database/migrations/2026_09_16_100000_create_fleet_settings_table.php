<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fleet_settings', function (Blueprint $table) {
            $table->id();
            // Formato "MM-DD": data (senza anno) in cui la flotta dovrebbe
            // passare a quella stagionalità di gomme.
            $table->string('winter_switch_date', 5)->default('11-15');
            $table->string('summer_switch_date', 5)->default('04-15');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fleet_settings');
    }
};
