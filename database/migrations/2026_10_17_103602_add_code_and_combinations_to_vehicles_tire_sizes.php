<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "position" (front/rear/either) sparisce da allowed_tire_sizes: la
     * carta di circolazione reale non assegna una posizione fissa a priori
     * a ciascuna misura, ma elenca misure numerate e una tabella di
     * combinazioni ammesse tra assi (vedi allowed_tire_size_combinations
     * sotto) che determina dove può andare ciascuna. I dati esistenti
     * ricevono un "code" sequenziale ("1","2",...) per veicolo; non è
     * possibile derivare le combinazioni dal vecchio campo position, quindi
     * vanno re-inserite a mano (documentato nella issue).
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->json('allowed_tire_size_combinations')->nullable()->after('allowed_tire_sizes');
        });

        DB::table('vehicles')->whereNotNull('allowed_tire_sizes')->orderBy('id')->each(function ($row) {
            $sizes = json_decode($row->allowed_tire_sizes, true) ?? [];

            if (empty($sizes) || ! is_array($sizes[0] ?? null) || array_key_exists('code', $sizes[0])) {
                return;
            }

            DB::table('vehicles')->where('id', $row->id)->update([
                'allowed_tire_sizes' => json_encode(array_values(array_map(
                    fn ($entry, $index) => ['size' => $entry['size'] ?? $entry, 'code' => (string) ($index + 1)],
                    $sizes,
                    array_keys($sizes)
                ))),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('allowed_tire_size_combinations');
        });

        DB::table('vehicles')->whereNotNull('allowed_tire_sizes')->orderBy('id')->each(function ($row) {
            $sizes = json_decode($row->allowed_tire_sizes, true) ?? [];

            if (empty($sizes) || ! is_array($sizes[0] ?? null) || ! array_key_exists('code', $sizes[0])) {
                return;
            }

            DB::table('vehicles')->where('id', $row->id)->update([
                'allowed_tire_sizes' => json_encode(array_map(
                    fn ($entry) => ['size' => $entry['size'] ?? '', 'position' => 'either'],
                    $sizes
                )),
            ]);
        });
    }
};
