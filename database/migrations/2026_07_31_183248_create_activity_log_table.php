<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActivityLogTable extends Migration
{
    public function up()
    {
        // Nome tabella/connessione fissi (non più letti da config, rimossa
        // in spatie/laravel-activitylog v5): questa migrazione è già stata
        // eseguita in produzione quando quella config esisteva ancora,
        // sempre con questi stessi valori di default — nessun cambio di
        // comportamento, solo rimozione della dipendenza da chiavi non più
        // presenti.
        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });
    }

    public function down()
    {
        Schema::dropIfExists('activity_log');
    }
}
