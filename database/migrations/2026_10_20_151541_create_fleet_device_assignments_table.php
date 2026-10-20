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
        Schema::create('fleet_device_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_device_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('previous_vehicle_id')->nullable()->constrained('vehicles')->onDelete('set null');
            $table->string('plate_snapshot')->nullable();
            $table->date('assigned_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fleet_device_assignments');
    }
};
