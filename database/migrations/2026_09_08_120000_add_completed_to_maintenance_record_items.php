<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_record_items', function (Blueprint $table) {
            $table->boolean('completed')->default(false)->after('itemable_type')
                ->comment('Indica se il guasto/scadenza è stato completato in questo appuntamento');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_record_items', function (Blueprint $table) {
            $table->dropColumn('completed');
        });
    }
};
