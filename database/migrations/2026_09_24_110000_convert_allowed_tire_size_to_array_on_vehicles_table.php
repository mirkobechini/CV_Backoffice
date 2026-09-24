<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un veicolo può avere più di una misura pneumatici consigliata (es.
     * assale anteriore/posteriore diversi): sostituisce la colonna stringa
     * singola con un array JSON.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->json('allowed_tire_sizes')->nullable()->after('vehicle_category');
        });

        DB::table('vehicles')->whereNotNull('allowed_tire_size')->orderBy('id')->each(function ($row) {
            DB::table('vehicles')->where('id', $row->id)->update([
                'allowed_tire_sizes' => json_encode([$row->allowed_tire_size]),
            ]);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('allowed_tire_size');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('allowed_tire_size')->nullable()->after('vehicle_category');
        });

        DB::table('vehicles')->whereNotNull('allowed_tire_sizes')->orderBy('id')->each(function ($row) {
            $sizes = json_decode($row->allowed_tire_sizes, true) ?? [];
            DB::table('vehicles')->where('id', $row->id)->update([
                'allowed_tire_size' => $sizes[0] ?? null,
            ]);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('allowed_tire_sizes');
        });
    }
};
