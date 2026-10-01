<?php

namespace App\Services;

use App\Models\Deadline;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\Tire;
use Illuminate\Support\Carbon;

class MaintenanceCompletionService
{
    public function __construct(
        private readonly DeadlineService $deadlineService,
        private readonly MileageLogService $mileageLogService,
        private readonly TireChangeService $tireChangeService,
    ) {
    }

    /**
     * Per un appuntamento "Cambio Gomme", collega le gomme da montare: una
     * o più esistenti (in magazzino, 1/2/4, validate in
     * ValidatesTireSelection) e/o di nuove appena descritte. Il montaggio
     * vero e proprio (con la scelta di cosa fare delle gomme sostituite)
     * avviene solo al completamento dell'appuntamento (vedi complete()),
     * non qui.
     */
    public function linkTireItems(MaintenanceRecord $maintenanceRecord, array $data): void
    {
        if (($data['activity_type'] ?? null) !== MaintenanceRecord::ACTIVITY_TIRE_CHANGE) {
            return;
        }

        $tires = collect();

        if (! empty($data['target_tire_ids'])) {
            $tires = Tire::whereIn('id', $data['target_tire_ids'])
                ->where('vehicle_id', $data['vehicle_id'])
                ->get();
        }

        if (! empty($data['new_tire_season'])) {
            $vehicle = $maintenanceRecord->vehicle;

            foreach (Tire::positionsForGroup($data['new_tire_group'] ?? null, $data['new_tire_position'] ?? null) as $position) {
                $tires->push(Tire::create([
                    'vehicle_id' => $data['vehicle_id'],
                    'season' => $data['new_tire_season'],
                    'position' => $position,
                    'brand' => $data['new_tire_brand'] ?? null,
                    'model_name' => $data['new_tire_model_name'] ?? null,
                    'size' => $data['new_tire_size'] ?? null,
                    'status' => Tire::STATUS_STORED,
                ]));
            }

            if ($warning = Tire::sizeMismatchWarning($data['new_tire_size'] ?? null, $vehicle)) {
                session()->flash('tire_size_warning', $warning);
            }
        }

        foreach ($tires as $tire) {
            $maintenanceRecord->items()->create([
                'itemable_id' => $tire->id,
                'itemable_type' => Tire::class,
                'completed' => false,
            ]);
        }
    }

    /**
     * Processa gli item marcati come completati in un appuntamento con data
     * di rientro: chiude i guasti e rinnova le scadenze.
     */
    public function processCompletedItems(MaintenanceRecord $maintenanceRecord, array $completedIssueIds, array $completedDeadlineIds): void
    {
        $maintenanceRecord->loadMissing(['items.itemable', 'vehicle.vehicleType']);

        // Chiudi i guasti completati (loop su modelli singoli, non query di
        // massa — vedi commento in update() sullo stesso tema).
        if (! empty($completedIssueIds)) {
            Issue::whereIn('id', $completedIssueIds)
                ->get()
                ->each(fn (Issue $issue) => $issue->update(['status' => 'closed']));
        }

        // Rinnova le scadenze completate
        $completedDeadlines = $maintenanceRecord->items
            ->where('itemable_type', Deadline::class)
            ->whereIn('itemable_id', $completedDeadlineIds)
            ->map(fn($item) => $item->itemable)
            ->filter();

        foreach ($completedDeadlines as $deadline) {
            if (in_array($deadline->type, [Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN, Deadline::TYPE_TAGLIANDO, Deadline::TYPE_CINGHIA], true)) {
                $this->renewDeadline($maintenanceRecord, $deadline);
            }
        }
    }

    /**
     * Rinnova una scadenza: la marca come rinnovata e crea la successiva.
     * La base temporale è la data di RIENTRO (return_date).
     */
    public function renewDeadline(MaintenanceRecord $maintenanceRecord, Deadline $deadline): void
    {
        $deadline->status = 'renewed';
        $deadline->is_renewed = true;
        // Il km rilevato all'appuntamento (se inserito) è il km del
        // veicolo al momento di QUESTA revisione: va sulla scadenza appena
        // rinnovata, non su quella successiva (che non è ancora avvenuta).
        // Prima veniva riportato solo per tagliando/cinghia, mai per
        // ministeriale/ossigeno.
        if ($maintenanceRecord->mileage_at_service !== null) {
            $deadline->last_mileage = $maintenanceRecord->mileage_at_service;
        }
        $deadline->save();

        // Il km rilevato all'appuntamento è una lettura reale del
        // contachilometri: la registriamo nello storico chilometraggi del
        // veicolo (vedi MileageLogService), non solo sulla scadenza. Una
        // sola chiamata qui copre tutti i tipi (ministeriale/ossigeno/
        // tagliando/cinghia), a prescindere da dove il km finisce salvato
        // più sotto.
        $this->mileageLogService->recordReading(
            $maintenanceRecord->vehicle,
            $maintenanceRecord->return_date ?? Carbon::today(),
            $maintenanceRecord->mileage_at_service,
        );

        // Il tagliando ha una logica dedicata: la scadenza temporale
        // parte dalla data di RIENTRO e la scadenza km dai km
        // inseriti + intervallo del tipo veicolo.
        if ($deadline->type === Deadline::TYPE_TAGLIANDO) {
            $this->renewTagliandoDeadline($maintenanceRecord, $deadline);
            return;
        }

        // La cinghia ha una logica dedicata (intervallo giorni + km).
        if ($deadline->type === Deadline::TYPE_CINGHIA) {
            $this->renewTimingBeltDeadline($maintenanceRecord, $deadline);
            return;
        }

        // Tutte le scadenze partono dalla data di RIENTRO.
        $baseDate = Carbon::parse($maintenanceRecord->return_date ?? Carbon::today());
        $nextDueDate = null;
        if ($deadline->type === Deadline::TYPE_MINISTERIAL && ($maintenanceRecord->vehicle->vehicleType?->regular_inspection_months ?? 0) > 0) {
            $monthsToAdd = (int) $maintenanceRecord->vehicle->vehicleType?->regular_inspection_months;
            $nextDueDate = $baseDate->copy()->addMonthsNoOverflow($monthsToAdd);
        } elseif ($deadline->type === Deadline::TYPE_OXYGEN && Deadline::supportsOxygenCheckForVehicle($maintenanceRecord->vehicle)) {
            $nextDueDate = $baseDate->copy()->addMonthsNoOverflow(Deadline::OXYGEN_CHECK_INTERVAL_MONTHS);
        }
        if ($nextDueDate) {
            // Stessa guardia anti-duplicati del rinnovo via form di
            // modifica (renews_deadline_id): prima questo controller aveva
            // una propria creazione senza quel collegamento, quindi la
            // guardia non poteva riconoscere una scadenza già creata da
            // qui, ed era possibile ottenerne un duplicato.
            $this->deadlineService->createNextOccurrence($deadline, $maintenanceRecord->vehicle, $nextDueDate);
        }
    }

    /**
     * Rinnova la scadenza della cinghia di distribuzione dopo un cambio.
     * La nuova scadenza riparte dalla data e dal chilometraggio del cambio.
     * Crea SEMPRE una nuova scadenza per mantenere lo storico completo (una
     * per cambio effettuato), ma solo se questa non ne ha già una
     * successiva collegata (guardia in DeadlineService::createNextOccurrence).
     */
    private function renewTimingBeltDeadline(MaintenanceRecord $maintenanceRecord, Deadline $deadline): void
    {
        $vehicle = $maintenanceRecord->vehicle;
        $baseDate = Carbon::parse($maintenanceRecord->return_date ?? Carbon::today());
        $baseKm = $maintenanceRecord->mileage_at_service ?? 0;
        $intervalDays = $vehicle->timingBeltIntervalDays();
        $nextDueDate = $intervalDays ? $baseDate->copy()->addDays($intervalDays) : null;

        $this->deadlineService->createNextOccurrence(
            $deadline,
            $vehicle,
            $nextDueDate,
            [
                'last_mileage' => $baseKm,
                'interval_km' => Deadline::TIMING_BELT_INTERVAL_KM,
                'interval_days' => $intervalDays,
            ]
        );
    }

    /**
     * Rinnova la scadenza del tagliando dopo il completamento.
     *
     * La scadenza temporale parte dalla data di RIENTRO del veicolo
     * (es. 18/10/2024 → 18/10/2025), mentre la scadenza km parte dai km
     * inseriti + l'intervallo del tipo veicolo (es. 16000 + 19000 = 35000).
     * Crea SEMPRE una nuova scadenza (una per tagliando effettuato), ma
     * solo se questa non ne ha già una successiva collegata.
     */
    private function renewTagliandoDeadline(MaintenanceRecord $maintenanceRecord, Deadline $deadline): void
    {
        // Base temporale: data di rientro
        $baseDate = Carbon::parse($maintenanceRecord->return_date ?? Carbon::today());
        $dueDate = $baseDate->copy()->addMonthsNoOverflow(Deadline::TAGLIANDO_INTERVAL_MONTHS);

        // Base km: km inseriti all'appuntamento + intervallo del tipo veicolo
        $baseKm = $maintenanceRecord->mileage_at_service;
        $intervalKm = (int) ($maintenanceRecord->vehicle->vehicleType?->regular_tagliando_km ?? 20000);

        $this->deadlineService->createNextOccurrence(
            $deadline,
            $maintenanceRecord->vehicle,
            $dueDate,
            [
                'last_mileage' => $baseKm,
                'interval_km' => $intervalKm,
                'interval_days' => Deadline::TAGLIANDO_INTERVAL_MONTHS * 30,
            ]
        );
    }
}
