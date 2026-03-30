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
        Schema::table('deadlines', function (Blueprint $table) {
            $table->enum('status', ['pending', 'expired', 'renewed', 'valid'])->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Aggiorna tutti i record con status 'valid' a 'pending' (o altro stato valido)
        DB::table('deadlines')->where('status', 'valid')->update(['status' => 'pending']);
        Schema::table('deadlines', function (Blueprint $table) {
            $table->enum('status', ['pending', 'expired', 'renewed'])->default('pending')->change();
        });
    }
};
