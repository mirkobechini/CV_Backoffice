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
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['brand', 'model']);
            $table->foreignId('brand_id')->nullable()->constrained()->onDelete('set null')->after('internal_code');
            $table->foreignId('car_model_id')->nullable()->constrained()->onDelete('set null')->after('brand_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropForeign(['car_model_id']);
            $table->dropColumn(['brand_id', 'car_model_id']);
            $table->string('brand')->nullable()->after('internal_code');
            $table->string('model')->nullable()->after('brand');
        });
    }
};
