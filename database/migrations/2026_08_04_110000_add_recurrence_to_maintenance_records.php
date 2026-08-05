<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->unsignedSmallInteger('recurrence_months')->nullable()->after('mileage_at_service')
                ->comment('Intervallo mesi per il prossimo tagliando');
            $table->unsignedInteger('recurrence_km')->nullable()->after('recurrence_months')
                ->comment('Intervallo km per il prossimo tagliando');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropColumn(['recurrence_months', 'recurrence_km']);
        });
    }
};