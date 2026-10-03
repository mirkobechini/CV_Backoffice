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
        Schema::table('equipment_issues', function (Blueprint $table) {
            $table->foreignId('equipment_maintenance_record_id')->nullable()
                ->after('equipment_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment_issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_maintenance_record_id');
        });
    }
};
