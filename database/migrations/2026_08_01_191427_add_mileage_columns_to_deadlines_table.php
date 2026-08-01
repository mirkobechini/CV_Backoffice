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
        Schema::table('deadlines', function (Blueprint $table) {
            $table->unsignedInteger('interval_km')->nullable()->after('is_renewed')->comment('Intervallo km (es. 15000 per tagliando, 100000 per cinghia)');
            $table->unsignedInteger('last_mileage')->nullable()->after('interval_km')->comment('Km all\'ultimo cambio');
            $table->unsignedInteger('interval_days')->nullable()->after('last_mileage')->comment('Intervallo giorni (es. 365 per tagliando, 3650 per cinghia)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deadlines', function (Blueprint $table) {
            $table->dropColumn(['interval_km', 'last_mileage', 'interval_days']);
        });
    }
};
