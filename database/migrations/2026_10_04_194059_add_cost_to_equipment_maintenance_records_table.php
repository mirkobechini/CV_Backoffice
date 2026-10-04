<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_maintenance_records', function (Blueprint $table) {
            $table->decimal('cost', 10, 2)->nullable()->after('activity_type');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_maintenance_records', function (Blueprint $table) {
            $table->dropColumn('cost');
        });
    }
};
