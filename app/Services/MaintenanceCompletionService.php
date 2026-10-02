<?php

namespace App\Services;

use App\Models\Deadline;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\Tire;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
     * Item gomma di questo appuntamento per cui il completamento chiederà
     * una disposizione (stored/retired): solo quelli non ancora completati
     * e per cui c'è davvero una gomma montata nella stessa posizione da
     * sostituire (altrimenti è un primo montaggio, nessuna scelta da fare).
     * Usata sia per costruire le regole di validazione in complete() sia
     * per il form del modale di completamento.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\MaintenanceRecordItem>
     */
    public function tireItemsRequiringDisposition(MaintenanceRecord $maintenanceRecord): \Illuminate\Support\Collection
    {
        $maintenanceRecord->loadMissing('items.itemable');

        return $maintenanceRecord->items
            ->where('itemable_type', Tire::class)
            ->where('completed', false)
            ->filter(fn ($item) => $item->itemable && $this->tireChangeService->findPreviousMountedTire($item->itemable));
    }

    /**
     * Completa un appuntamento: chiude/mantiene in lavorazione il guasto
     * collegato, rinnova le scadenze marcate come risolte (inclusa
     * l'eventuale cinghia di distribuzione) e monta le gomme collegate non
     * ancora completate, applicando la disposizione scelta per quelle
     * sostituite. Tutto nella stessa transazione: un fallimento a metà
     * (es. su TireChangeService) lascerebbe altrimenti l'appuntamento
     * segnato come rientrato ma guasti/scadenze/gomme incoerenti.
     */
    public function complete(MaintenanceRecord $maintenanceRecord, array $data): void
    {
        $maintenanceRecord->loadMissing(['items.itemable', 'vehicle.vehicleType']);

        $issues = $maintenanceRecord->items->where('itemable_type', Issue::class);
        $deadlines = $maintenanceRecord->items->where('itemable_type', Deadline::class);
        $tireItems = $maintenanceRecord->items->where('itemable_type', Tire::class);

        DB::transaction(function () use ($maintenanceRecord, $data, $issues, $deadlines, $tireItems) {
            // 1) complete maintenance
            // Se l'utente ha già indicato una data di rientro (es. un tagliando
            // registrato retroattivamente), la rispettiamo. Altrimenti usiamo oggi.
            if (! $maintenanceRecord->return_date) {
                $maintenanceRecord->return_date = Carbon::today();
            }
            $maintenanceRecord->save();

            // 2) update issues
            foreach ($issues as $item) {
                $issue = $item->itemable;
                if ($issue) {
                    if ((bool) $data['issue_resolved']) {
                        $issue->status = 'closed';
                        $issue->save();
                    } else {
                        $issue->status = 'in_progress';
                        $issue->save();
                    }
                }
            }

            // 3) update deadlines + create next ones
            foreach ($deadlines as $item) {
                $deadline = $item->itemable;
                if (! $deadline || ! in_array($deadline->type, [Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN, Deadline::TYPE_TAGLIANDO], true)) {
                    continue;
                }

                if ((bool) $data['issue_resolved']) {
                    $this->renewDeadline($maintenanceRecord, $deadline);
                } else {
                    $deadline->status = 'pending';
                    $deadline->save();
                }
            }

            // 4) Cambio cinghia distribuzione: riparte la scadenza dalla data
            //    e dal chilometraggio del cambio effettuato. Non è collegata
            //    come MaintenanceRecordItem (il passo 3 sopra esclude apposta
            //    TYPE_CINGHIA), quindi va trovata qui sul veicolo.
            if ($maintenanceRecord->activity_type === MaintenanceRecord::ACTIVITY_TIMING_BELT && (bool) $data['issue_resolved']) {
                $timingBeltDeadline = $maintenanceRecord->vehicle->deadlines()
                    ->where('type', Deadline::TYPE_CINGHIA)
                    ->where('is_renewed', false)
                    ->latest('due_date')
                    ->first();

                if ($timingBeltDeadline) {
                    $this->renewDeadline($maintenanceRecord, $timingBeltDeadline);
                }
            }

            // 5) Cambio gomme: monta i set collegati (uno o più, es. anteriori
            // + posteriori insieme), applicando alle gomme sostituite la
            // disposizione scelta (magazzino o dismesse). Le chiamate in
            // sequenza si compongono correttamente anche quando un set
            // "full" già montato va diviso tra i due nuovi assi montati
            // (vedi TireChangeService).
            foreach ($tireItems as $tireItem) {
                if ($tireItem->completed) {
                    continue;
                }

                // Disposizione scelta indipendentemente per ogni gomma
                // sostituita (es. 1 dismessa e 3 in magazzino nello stesso
                // cambio): 'stored' come default innocuo quando non c'è
                // nulla da smontare in quella posizione (primo montaggio),
                // perché in quel caso non viene comunque applicato (vedi
                // TireChangeService::findPreviousMountedTire()).
                $previousDisposition = $data['previous_disposition'][$tireItem->itemable_id] ?? Tire::STATUS_STORED;

                $this->tireChangeService->recordChange(
                    $tireItem->itemable,
                    $maintenanceRecord->return_date,
                    $maintenanceRecord->mileage_at_service,
                    $previousDisposition,
                    "Appuntamento del {$maintenanceRecord->appointment_date_formatted}",
                );
                $tireItem->update(['completed' => true]);
            }
        });
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
    private function renewDeadline(MaintenanceRecord $maintenanceRecord, Deadline $deadline): void
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
