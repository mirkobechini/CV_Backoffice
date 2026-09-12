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
        Schema::table('deadlines', function (Blueprint $table) {
            // Collega una scadenza (es. la nuova Revisione Ministeriale) a
            // quella che ha automaticamente rinnovato alla creazione, per
            // poter ripristinare lo stato precedente ("scaduta") se questa
            // viene eliminata.
            $table->foreignId('renews_deadline_id')
                ->nullable()
                ->after('is_renewed')
                ->constrained('deadlines')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deadlines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('renews_deadline_id');
        });
    }
};
