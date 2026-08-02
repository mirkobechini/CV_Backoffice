<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Nota: foreignId() crea già un indice sulle FK (vehicle_id, provider_id, ecc.).
     * Qui aggiungiamo indici su colonne usate in WHERE/ORDER BY/GROUP BY che non sono FK.
     */
    public function up(): void
    {
        // deadlines: usato in filtri per tipo, status, ordinamento per data
        Schema::table('deadlines', function (Blueprint $table) {
            $table->index(['type', 'status'], 'idx_deadlines_type_status');
            $table->index('due_date', 'idx_deadlines_due_date');
        });

        // issues: filtrato per status (scope open/in_progress)
        Schema::table('issues', function (Blueprint $table) {
            $table->index('status', 'idx_issues_status');
            $table->index('event_date', 'idx_issues_event_date');
        });

        // mileage_logs: ordinato per log_date, filtrato per vehicle_id (già indicizzato da FK)
        Schema::table('mileage_logs', function (Blueprint $table) {
            $table->index('log_date', 'idx_mileage_logs_log_date');
        });

        // equipment: filtrato per expiration_date (scope expiringSoon)
        Schema::table('equipment', function (Blueprint $table) {
            $table->index('expiration_date', 'idx_equipment_expiration_date');
        });

        // maintenance_records: filtrato per appointment_date e return_date
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->index('appointment_date', 'idx_maintenance_records_appointment_date');
            $table->index('return_date', 'idx_maintenance_records_return_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deadlines', function (Blueprint $table) {
            $table->dropIndex('idx_deadlines_type_status');
            $table->dropIndex('idx_deadlines_due_date');
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->dropIndex('idx_issues_status');
            $table->dropIndex('idx_issues_event_date');
        });

        Schema::table('mileage_logs', function (Blueprint $table) {
            $table->dropIndex('idx_mileage_logs_log_date');
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->dropIndex('idx_equipment_expiration_date');
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropIndex('idx_maintenance_records_appointment_date');
            $table->dropIndex('idx_maintenance_records_return_date');
        });
    }
};
