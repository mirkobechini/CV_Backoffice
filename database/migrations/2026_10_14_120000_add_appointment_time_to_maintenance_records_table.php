<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Facoltativo: a differenza della data, l'orario non serve per nessuna
     * logica di scadenza/ordinamento esistente, solo da mostrare quando
     * l'utente lo imposta. Colonna stringa ("14:30") invece di un vero
     * tipo TIME per evitare differenze di arrotondamento tra i driver DB
     * (MySQL in produzione, SQLite nei test).
     */
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->string('appointment_time', 5)->nullable()->after('appointment_date');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropColumn('appointment_time');
        });
    }
};
