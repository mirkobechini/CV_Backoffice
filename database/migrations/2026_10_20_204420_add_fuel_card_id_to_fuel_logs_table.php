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
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->foreignId('fuel_card_id')->nullable()->after('vehicle_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->dropForeign(['fuel_card_id']);
            $table->dropColumn('fuel_card_id');
        });
    }
};
