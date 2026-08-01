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
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->foreignId('issue_id')->nullable()->change();
            $table->foreignId('deadline_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->foreignId('issue_id')->nullable(false)->change();
            $table->foreignId('deadline_id')->nullable()->change();
        });
    }
};
