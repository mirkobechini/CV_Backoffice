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
        Schema::create('tires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->enum('season', ['summer', 'winter', 'all_season']);
            $table->unsignedTinyInteger('quantity')->default(4);
            $table->string('brand')->nullable();
            $table->string('model_name')->nullable();
            $table->string('size')->nullable();
            $table->enum('status', ['mounted', 'stored', 'retired'])->default('stored');
            $table->date('mounted_date')->nullable();
            $table->unsignedInteger('mounted_mileage')->nullable();
            $table->date('next_change_date')->nullable();
            $table->unsignedInteger('next_change_mileage')->nullable();
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
        Schema::dropIfExists('tires');
    }
};
