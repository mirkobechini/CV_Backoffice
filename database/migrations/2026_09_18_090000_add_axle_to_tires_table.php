<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Un cambio gomme può riguardare solo un asse (es. solo le 2 anteriori)
     * invece dell'intero set da 4: "full" resta il caso normale, "front"/
     * "rear" permettono a un veicolo di avere contemporaneamente un set
     * anteriore e uno posteriore montati separatamente.
     */
    public function up(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->enum('axle', ['full', 'front', 'rear'])->default('full')->after('season');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->dropColumn('axle');
        });
    }
};
