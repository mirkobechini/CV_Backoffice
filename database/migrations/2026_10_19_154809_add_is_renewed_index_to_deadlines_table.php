<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * is_renewed è filtrato in quasi ogni query sulle scadenze non ancora
     * rinnovate (DeadlineService, DashboardController, GenerateNotifications)
     * ma non era mai stato indicizzato (audit 2026-10-19). Composito con
     * due_date, il secondo filtro più comune sulle stesse query.
     */
    public function up(): void
    {
        Schema::table('deadlines', function (Blueprint $table) {
            $table->index(['is_renewed', 'due_date'], 'idx_deadlines_is_renewed_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('deadlines', function (Blueprint $table) {
            $table->dropIndex('idx_deadlines_is_renewed_due_date');
        });
    }
};
