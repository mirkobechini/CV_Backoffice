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
        Schema::create('vehicle_catalog_lookups', function (Blueprint $table) {
            $table->id();
            $table->string('type_code');
            $table->string('variant_code');
            $table->string('version_code');
            $table->boolean('found');
            $table->string('fuel_type')->nullable();
            $table->unsignedInteger('engine_displacement_cc')->nullable();
            $table->unsignedInteger('engine_power_kw')->nullable();
            $table->unsignedInteger('max_mass_kg')->nullable();
            $table->timestamp('looked_up_at');
            $table->timestamps();
            $table->unique(['type_code', 'variant_code', 'version_code'], 'vehicle_catalog_lookups_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_catalog_lookups');
    }
};
