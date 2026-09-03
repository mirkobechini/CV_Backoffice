<?php

use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\EquipmentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    /**
     * Backfill dei dati esistenti per allineare le modifiche logiche
     * introdotte in questa sessione:
     *
     * 1. Equipment: calcola expiration_date per quelli che hanno una
     *    revision_date e un tipo con regular_inspection_months, ma nessuna
     *    expiration_date impostata.
     *
     * 2. Deadline: sincronizza lo stato persistito con le regole
     *    temporali/km correnti (syncStatusesFromRules).
     */
    public function up(): void
    {
        // 1) Backfill expiration_date sugli equipaggiamenti
        $equipments = Equipment::with('equipmentType')
            ->whereNull('expiration_date')
            ->whereNotNull('revision_date')
            ->get();

        $updated = 0;
        foreach ($equipments as $equipment) {
            $regularMonths = $equipment->equipmentType?->regular_inspection_months;
            if (!$regularMonths || $regularMonths <= 0) {
                continue;
            }

            $equipment->expiration_date = Carbon::parse($equipment->revision_date)
                ->addMonthsNoOverflow((int) $regularMonths)
                ->toDateString();
            $equipment->save();
            $updated++;
        }

        // 2) Sincronizza lo stato delle scadenze con le regole correnti
        $deadlines = Deadline::query()->get();
        Deadline::syncStatusesFromRules($deadlines);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non reversibile: i dati backfillati non possono essere ripristinati
        // in modo affidabile. La migrazione è idempotente e sicura da rieseguire.
    }
};
