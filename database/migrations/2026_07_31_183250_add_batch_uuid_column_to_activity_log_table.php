<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBatchUuidColumnToActivityLogTable extends Migration
{
    public function up()
    {
        // Vedi commento in create_activity_log_table: nome tabella/
        // connessione fissi, non più da config (rimossa in v5).
        Schema::table('activity_log', function (Blueprint $table) {
            $table->uuid('batch_uuid')->nullable()->after('properties');
        });
    }

    public function down()
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropColumn('batch_uuid');
        });
    }
}
