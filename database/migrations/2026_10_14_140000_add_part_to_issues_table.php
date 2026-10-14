<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable a livello di DB per non rompere i guasti già esistenti
     * (allineati poi con la revisione assistita dall'AI, vedi
     * IssuePartBackfillController): la validazione di Store/UpdateIssueRequest
     * la rende comunque obbligatoria per ogni nuovo guasto da qui in poi.
     */
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->string('part')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn('part');
        });
    }
};
