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
        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Backfill: crea un gruppo di default e assegna tutti i veicoli esistenti.
        // Questo garantisce che i dati di produzione esistenti non vengano persi.
        $groupName = 'Associazione di default';
        $groupId = DB::table('groups')->insertGetId([
            'name' => $groupName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('vehicles')->whereNull('group_id')->update(['group_id' => $groupId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });
    }
};
