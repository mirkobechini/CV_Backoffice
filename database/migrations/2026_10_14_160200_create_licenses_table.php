<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->onDelete('cascade');
            $table->string('tier');
            $table->unsignedInteger('max_vehicles')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->json('features')->nullable();
            $table->decimal('list_price_eur', 8, 2)->nullable();
            $table->foreignId('discount_code_id')->nullable()->constrained('discount_codes')->nullOnDelete();
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->decimal('final_price_eur', 8, 2)->nullable();
            $table->date('starts_at');
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
