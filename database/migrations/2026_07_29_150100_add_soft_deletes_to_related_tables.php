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
        Schema::table('vehicles', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('issues', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('deadlines', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('issues', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('deadlines', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
