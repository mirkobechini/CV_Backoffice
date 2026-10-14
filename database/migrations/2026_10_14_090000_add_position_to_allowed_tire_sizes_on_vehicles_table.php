<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * allowed_tire_sizes passa da array di stringhe (es. ["225/75R16C"]) ad
     * array di oggetti {size, position}: alcune misure del libretto valgono
     * solo per l'anteriore o il posteriore, non per entrambi. I dati
     * esistenti diventano "either" (nessuna restrizione), lo stesso
     * comportamento di prima — nessuna gomma che passava il controllo oggi
     * smette di passarlo dopo questa migrazione.
     */
    public function up(): void
    {
        DB::table('vehicles')->whereNotNull('allowed_tire_sizes')->orderBy('id')->each(function ($row) {
            $sizes = json_decode($row->allowed_tire_sizes, true) ?? [];

            if (empty($sizes) || is_array($sizes[0] ?? null)) {
                return;
            }

            DB::table('vehicles')->where('id', $row->id)->update([
                'allowed_tire_sizes' => json_encode(array_map(
                    fn ($size) => ['size' => $size, 'position' => 'either'],
                    $sizes
                )),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('vehicles')->whereNotNull('allowed_tire_sizes')->orderBy('id')->each(function ($row) {
            $sizes = json_decode($row->allowed_tire_sizes, true) ?? [];

            if (empty($sizes) || ! is_array($sizes[0] ?? null)) {
                return;
            }

            DB::table('vehicles')->where('id', $row->id)->update([
                'allowed_tire_sizes' => json_encode(array_map(
                    fn ($entry) => $entry['size'] ?? $entry,
                    $sizes
                )),
            ]);
        });
    }
};
