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
        Schema::create('vehicle_type_equipment_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('equipment_type_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('required_quantity')->default(1);
            $table->unique(['vehicle_type_id', 'equipment_type_id'],'vt_eq_req_vehicle_equipment_unique'); // Unique constraint to prevent duplicate entries(rinominato x nome troppo lungo)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_type_equipment_requirements');
    }
};
