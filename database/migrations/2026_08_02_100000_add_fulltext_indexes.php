<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Aggiunge indici FULLTEXT per la ricerca testuale su campi "lunghi"
     * (TEXT, VARCHAR di grandi dimensioni) dove LIKE %term% non scala.
     *
     * I campi "corti" (status, type, internal_code, license_plate) rimangono
     * con LIKE perché MySQL ottimizza comunque bene su VARCHAR brevi.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return; // SQLite non supporta FULLTEXT
        }

        // issues.description: campo TEXT dove LIKE %term% è molto lento su volumi elevati
        Schema::table('issues', function ($table) {
            $table->fullText('description', 'idx_issues_description_fulltext');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('issues', function ($table) {
            $table->dropIndex('idx_issues_description_fulltext');
        });
    }
};