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
        Schema::create('issue_tire', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->onDelete('cascade');
            $table->foreignId('tire_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['issue_id', 'tire_id']);
        });

        DB::table('issues')->whereNotNull('tire_id')->select('id', 'tire_id')->get()->each(function ($issue) {
            DB::table('issue_tire')->insert([
                'issue_id' => $issue->id,
                'tire_id' => $issue->tire_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tire_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->foreignId('tire_id')->nullable()->after('vehicle_id')->constrained()->onDelete('set null');
        });

        DB::table('issue_tire')->orderBy('issue_id')->get()->groupBy('issue_id')->each(function ($rows, $issueId) {
            DB::table('issues')->where('id', $issueId)->update(['tire_id' => $rows->first()->tire_id]);
        });

        Schema::dropIfExists('issue_tire');
    }
};
