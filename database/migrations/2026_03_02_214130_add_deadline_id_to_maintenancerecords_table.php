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
        Schema::table('maintenancerecords', function (Blueprint $table) {
            $table->foreignId('deadline_id')->nullable()->unique()->after('issue_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenancerecords', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deadline_id'); //droppa anche la colonna
        });
    }
};
