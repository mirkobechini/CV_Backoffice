<?php

use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\Vehicle;
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
     * 2. Cinghia distribuzione: genera la scadenza per i veicoli con
     *    has_timing_belt che non ne hanno una.
     *
     * 3. Tagliando: genera il primo tagliando per i veicoli che non ne
     *    hanno una (scade al primo tra 1 anno o i km configurati).
     *
     * 4. is_renewed: corregge i record con status='renewed' ma
     *    is_renewed=false (rinnovi persi dal bug del complete()).
     *
     * 5. Deadline: sincronizza lo stato persistito con le regole
     *    temporali/km correnti (syncStatusesFromRules).
     */
    public function up(): void
    {
        // 1) Backfill expiration_date sugli equipaggiamenti
        $equipments = Equipment::with('equipmentType')
            ->whereNull('expiration_date')
            ->whereNotNull('revision_date')
            ->get();

        foreach ($equipments as $equipment) {
            $regularMonths = $equipment->equipmentType?->regular_inspection_months;
            if (!$regularMonths || $regularMonths <= 0) {
                continue;
            }

            $equipment->expiration_date = Carbon::parse($equipment->revision_date)
                ->addMonthsNoOverflow((int) $regularMonths)
                ->toDateString();
            $equipment->save();
        }

        // 2) Cinghia distribuzione per veicoli esistenti
        $vehiclesWithBelt = Vehicle::with('vehicleType')
            ->where('has_timing_belt', true)
            ->whereHas('deadlines', fn($q) => $q->where('type', Deadline::TYPE_CINGHIA), '=', 0)
            ->get();

        foreach ($vehiclesWithBelt as $vehicle) {
            if (!$vehicle->immatricolation_date) {
                continue;
            }
            $dueDate = Carbon::parse($vehicle->immatricolation_date)
                ->addDays(Deadline::TIMING_BELT_INTERVAL_DAYS);
            Deadline::create([
                'vehicle_id' => $vehicle->id,
                'type' => Deadline::TYPE_CINGHIA,
                'due_date' => $dueDate->toDateString(),
                'interval_km' => Deadline::TIMING_BELT_INTERVAL_KM,
                'last_mileage' => 0,
                'interval_days' => Deadline::TIMING_BELT_INTERVAL_DAYS,
            ]);
        }

        // 3) Primo tagliando per veicoli esistenti
        $vehiclesWithoutTagliando = Vehicle::with('vehicleType')
            ->whereDoesntHave('deadlines', fn($q) => $q->where('type', Deadline::TYPE_TAGLIANDO))
            ->get();

        foreach ($vehiclesWithoutTagliando as $vehicle) {
            if (!$vehicle->immatricolation_date) {
                continue;
            }
            $firstKm = (int) ($vehicle->vehicleType?->first_tagliando_km ?? 25000);
            $dueDate = Carbon::parse($vehicle->immatricolation_date)
                ->addMonthsNoOverflow(Deadline::TAGLIANDO_INTERVAL_MONTHS);
            Deadline::create([
                'vehicle_id' => $vehicle->id,
                'type' => Deadline::TYPE_TAGLIANDO,
                'due_date' => $dueDate->toDateString(),
                'interval_km' => $firstKm,
                'last_mileage' => 0,
            ]);
        }

        // 4) Corregge i rinnovi persi: status='renewed' ma is_renewed=false
        Deadline::where('status', Deadline::STATUS_RENEWED)
            ->where('is_renewed', false)
            ->update(['is_renewed' => true]);

        // 5) Sincronizza lo stato delle scadenze con le regole correnti
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
