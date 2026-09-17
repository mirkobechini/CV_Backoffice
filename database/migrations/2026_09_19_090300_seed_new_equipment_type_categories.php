<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Aggiunge i nuovi tipi di attrezzatura con categoria dedicata
     * (DAE, LUCAS, LIFEPAK, Aspiratore/LSU) se non già presenti, così
     * sono disponibili subito senza doverli creare manualmente. Gli
     * intervalli di revisione/collaudo restano vuoti: li imposta chi
     * gestisce la flotta in base alle indicazioni del produttore.
     */
    public function up(): void
    {
        $now = now();

        foreach (['DAE', 'LUCAS', 'LIFEPAK', 'Aspiratore (LSU)'] as $name) {
            $exists = DB::table('equipment_types')->where('name', $name)->exists();

            if (! $exists) {
                DB::table('equipment_types')->insert([
                    'name' => $name,
                    'category' => match ($name) {
                        'DAE' => 'dae',
                        'LUCAS' => 'lucas',
                        'LIFEPAK' => 'lifepak',
                        'Aspiratore (LSU)' => 'lsu',
                    },
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('equipment_types')->whereIn('name', ['DAE', 'LUCAS', 'LIFEPAK', 'Aspiratore (LSU)'])->delete();
    }
};
