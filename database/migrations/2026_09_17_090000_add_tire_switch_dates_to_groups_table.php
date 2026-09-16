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
        Schema::table('groups', function (Blueprint $table) {
            // Formato "MM-DD": data (senza anno) in cui la flotta di QUESTO
            // gruppo dovrebbe passare a quella stagionalità di gomme.
            // Per gruppo e non globale: due associazioni diverse che usano
            // la stessa installazione possono avere flotte con esigenze
            // stagionali diverse.
            $table->string('winter_switch_date', 5)->default('11-15');
            $table->string('summer_switch_date', 5)->default('04-15');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn(['winter_switch_date', 'summer_switch_date']);
        });
    }
};
