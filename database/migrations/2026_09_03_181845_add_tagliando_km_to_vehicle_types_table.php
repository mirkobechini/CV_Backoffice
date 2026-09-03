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
        Schema::table('vehicle_types', function (Blueprint $table) {
            $table->unsignedInteger('first_tagliando_km')->nullable()->after('regular_inspection_months')->comment('Km per il primo tagliando (da veicolo nuovo)');
            $table->unsignedInteger('regular_tagliando_km')->nullable()->after('first_tagliando_km')->comment('Km per i tagliandi successivi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_types', function (Blueprint $table) {
            $table->dropColumn(['first_tagliando_km', 'regular_tagliando_km']);
        });
    }
};
