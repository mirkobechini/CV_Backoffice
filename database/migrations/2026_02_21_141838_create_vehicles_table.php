<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('license_plate', 7)->unique();
            $table->string('internal_code', 4)->nullable();
            $table->string('brand');
            $table->string('model');
            $table->enum('fuel_type', ['benzina', 'diesel', 'elettrico', 'ibrido'])->nullable();
            $table->enum('type', ['auto', 'mezzo attrezzato', 'ambulanza'])->nullable();
            $table->date('immatricolation_date');
            $table->string('registration_card_path')->nullable();
            $table->date('warranty_original_expiration_date')->nullable();
            $table->boolean('has_warranty_extension')->default(false);
            $table->date('warranty_expiration_date')->nullable();
            $table->timestamps();
        });

        DB::statement("
        ALTER TABLE vehicles
        ADD CONSTRAINT chk_vehicles_license_plate
        CHECK (license_plate REGEXP '^[A-Z]{2}[0-9]{3}[A-Z]{2}$')
        ");

        DB::statement("
        ALTER TABLE vehicles
        ADD CONSTRAINT chk_vehicles_internal_code
        CHECK (internal_code IS NULL OR internal_code REGEXP '^[0-9]{4}$')
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
