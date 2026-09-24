<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Passa da "una riga = un set di N gomme (axle+quantity)" a "una riga =
     * una gomma fisica" (position: front_left/front_right/rear_left/
     * rear_right). Necessario per gestire cambi parziali (1, 2 o 4 gomme
     * in un appuntamento) e la singola gomma dentro un set già salvato.
     *
     * Per ogni riga esistente con quantity > 1, la riga ORIGINALE viene
     * riassegnata alla prima posizione e mantiene il suo id (così le
     * cronologie collegate — tire_changes, issues.tire_id — restano
     * valide senza bisogno di rimappare nulla); le posizioni aggiuntive
     * diventano nuove righe clonate dagli stessi dati.
     *
     * Le righe con quantity = 1 (es. un asse "spaccato" da un set full da
     * TireChangeService) non permettono di sapere se erano la gomma
     * sinistra o destra dell'asse: restano assegnate al lato "sinistro"
     * per convenzione, correggibili a mano se necessario. Stessa
     * convenzione (riempimento da front_left in poi) per axle=full con
     * quantity 1-3: il form validava axle e quantity indipendentemente,
     * quindi questa combinazione era raggiungibile anche se non tipica
     * (in origine mancava qui un ramo dedicato, che scartava silenziosamente
     * le gomme oltre la prima per axle=full con quantity 2 o 3).
     */
    public function up(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->string('position')->nullable()->after('axle');
        });

        DB::table('tires')->orderBy('id')->chunk(100, function ($tires) {
            foreach ($tires as $tire) {
                $positions = match (true) {
                    $tire->axle === 'front' && $tire->quantity >= 2 => ['front_left', 'front_right'],
                    $tire->axle === 'rear' && $tire->quantity >= 2 => ['rear_left', 'rear_right'],
                    $tire->axle === 'rear' => ['rear_left'],
                    $tire->axle === 'full' => array_slice(
                        ['front_left', 'front_right', 'rear_left', 'rear_right'],
                        0,
                        max(1, min(4, (int) $tire->quantity))
                    ),
                    default => ['front_left'],
                };

                $keepPosition = array_shift($positions);
                DB::table('tires')->where('id', $tire->id)->update(['position' => $keepPosition]);

                foreach ($positions as $position) {
                    $clone = (array) $tire;
                    unset($clone['id']);
                    $clone['position'] = $position;
                    $clone['created_at'] = $tire->created_at;
                    $clone['updated_at'] = now();
                    DB::table('tires')->insert($clone);
                }
            }
        });

        Schema::table('tires', function (Blueprint $table) {
            $table->dropColumn(['axle', 'quantity']);
        });

        Schema::table('tires', function (Blueprint $table) {
            $table->string('position')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Ricostruisce le colonne ma non riunisce le posizioni in set: ogni
     * riga torna un "asse" con quantity 1 (perdita di fedeltà accettata,
     * coerente con come le altre migrazioni di questo progetto trattano
     * le rotture di schema non banalmente reversibili).
     */
    public function down(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->enum('axle', ['full', 'front', 'rear'])->default('full')->after('season');
            $table->unsignedTinyInteger('quantity')->default(1)->after('axle');
        });

        DB::table('tires')->update([
            'axle' => DB::raw("CASE WHEN position IN ('front_left', 'front_right') THEN 'front' ELSE 'rear' END"),
            'quantity' => 1,
        ]);

        Schema::table('tires', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
