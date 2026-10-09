<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una polizza può avere più coperture insieme (es. RCA + Kasko), ciascuna
     * con il proprio costo: sostituisce le colonne singole insurance_coverage_type
     * (una sola copertura) e insurance_premium (un solo totale) su deadlines.
     * Migra i dati esistenti (una riga per ogni polizza già presente) prima
     * di droppare le vecchie colonne.
     */
    public function up(): void
    {
        Schema::create('deadline_insurance_coverages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deadline_id')->constrained()->cascadeOnDelete();
            $table->string('coverage_type');
            $table->decimal('cost', 10, 2);
            $table->timestamps();

            $table->unique(['deadline_id', 'coverage_type']);
        });

        DB::table('deadlines')
            ->where('type', 'Assicurazione')
            ->whereNotNull('insurance_coverage_type')
            ->select('id', 'insurance_coverage_type', 'insurance_premium')
            ->orderBy('id')
            ->each(function ($deadline) {
                DB::table('deadline_insurance_coverages')->insert([
                    'deadline_id' => $deadline->id,
                    'coverage_type' => $deadline->insurance_coverage_type,
                    'cost' => $deadline->insurance_premium ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('deadlines', function (Blueprint $table) {
            $table->dropColumn(['insurance_premium', 'insurance_coverage_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deadlines', function (Blueprint $table) {
            $table->decimal('insurance_premium', 10, 2)->nullable()->after('insurance_policy_number');
            $table->string('insurance_coverage_type')->nullable()->after('insurance_premium');
        });

        // Una polizza può avere più coperture: nel rollback ne riportiamo
        // solo la prima (per costo decrescente) sulle colonne singole,
        // perdendo le altre — accettabile per un rollback di sviluppo.
        DB::table('deadline_insurance_coverages')
            ->orderBy('deadline_id')
            ->orderByDesc('cost')
            ->get()
            ->groupBy('deadline_id')
            ->each(function ($coverages, $deadlineId) {
                $first = $coverages->first();
                DB::table('deadlines')->where('id', $deadlineId)->update([
                    'insurance_coverage_type' => $first->coverage_type,
                    'insurance_premium' => $first->cost,
                ]);
            });

        Schema::dropIfExists('deadline_insurance_coverages');
    }
};
