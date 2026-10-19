<?php

namespace App\Services;

use App\Models\Deadline;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRecordItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MaintenanceCompletionService
{
    public function __construct(
        private readonly DeadlineService $deadlineService,
        private readonly MileageLogService $mileageLogService,
        private readonly TireDispositionService $tireDispositionService,
    ) {}

    /**
     * @see TireDispositionService::linkTireItems()
     */
    public function linkTireItems(MaintenanceRecord $maintenanceRecord, array $data): void
    {
        $this->tireDispositionService->linkTireItems($maintenanceRecord, $data);
    }

    /**
     * @see TireDispositionService::tireItemsRequiringDisposition()
     *
     * @return Collection<int, MaintenanceRecordItem>
     */
    public function tireItemsRequiringDisposition(MaintenanceRecord $maintenanceRecord): Collection
    {
        return $this->tireDispositionService->tireItemsRequiringDisposition($maintenanceRecord);
    }

    /**
     * Guasti collegati a questo appuntamento, per cui complete() chiederà
     * un sì/no di risoluzione indipendente per ciascuno — non più un'unica
     * scelta valida per tutti (bug segnalato nell'audit 2026-10-19). Usata
     * sia per costruire le regole di validazione in complete() sia per il
     * form del modale di completamento.
     *
     * @return Collection<int, Issue>
     */
    public function linkedIssues(MaintenanceRecord $maintenanceRecord): Collection
    {
        $maintenanceRecord->loadMissing('items.itemable');

        return $maintenanceRecord->items
            ->where('itemable_type', Issue::class)
            ->map(fn ($item) => $item->itemable)
            ->filter()
            ->values();
    }

    /**
     * Scadenze collegate a questo appuntamento per cui esiste una logica di
     * rinnovo (la cinghia non è mai collegata come item, vedi complete()):
     * stesso sì/no indipendente per ciascuna di linkedIssues() sopra.
     *
     * @return Collection<int, Deadline>
     */
    public function linkedDeadlines(MaintenanceRecord $maintenanceRecord): Collection
    {
        $maintenanceRecord->loadMissing('items.itemable');

        return $maintenanceRecord->items
            ->where('itemable_type', Deadline::class)
            ->map(fn ($item) => $item->itemable)
            ->filter(fn (?Deadline $deadline) => $deadline && in_array($deadline->type, [
                Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN, Deadline::TYPE_TAGLIANDO,
            ], true))
            ->values();
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

        DB::transaction(function () use ($maintenanceRecord, $data, $issues, $deadlines) {
            // 1) complete maintenance
            // Se l'utente ha già indicato una data di rientro (es. un tagliando
            // registrato retroattivamente), la rispettiamo. Altrimenti usiamo oggi.
            if (! $maintenanceRecord->return_date) {
                $maintenanceRecord->return_date = Carbon::today();
            }
            $maintenanceRecord->save();

            // 2) update issues — un sì/no indipendente per ciascuno
            // (data['resolved_issues'][$issue->id]), non più un'unica
            // scelta per tutti (bug segnalato nell'audit 2026-10-19).
            foreach ($issues as $item) {
                $issue = $item->itemable;
                if ($issue) {
                    if ((bool) ($data['resolved_issues'][$issue->id] ?? false)) {
                        $issue->status = 'closed';
                        $issue->save();
                    } else {
                        $issue->status = 'in_progress';
                        $issue->save();
                    }
                }
            }

            // 3) update deadlines + create next ones — stesso sì/no
            // indipendente per ciascuna (data['resolved_deadlines'][$id]).
            foreach ($deadlines as $item) {
                $deadline = $item->itemable;
                if (! $deadline || ! in_array($deadline->type, [Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN, Deadline::TYPE_TAGLIANDO], true)) {
                    continue;
                }

                if ((bool) ($data['resolved_deadlines'][$deadline->id] ?? false)) {
                    $this->renewDeadline($maintenanceRecord, $deadline);
                } else {
                    $deadline->status = 'pending';
                    $deadline->save();
                }
            }

            // 4) Cambio cinghia distribuzione: riparte la scadenza dalla data
            //    e dal chilometraggio del cambio effettuato. Non è collegata
            //    come MaintenanceRecordItem (il passo 3 sopra esclude apposta
            //    TYPE_CINGHIA), quindi va trovata qui sul veicolo. Domanda
            //    dedicata (data['timing_belt_completed']), indipendente dal
            //    sì/no degli eventuali guasti/scadenze collegati allo stesso
            //    appuntamento.
            if ($maintenanceRecord->activity_type === MaintenanceRecord::ACTIVITY_TIMING_BELT && (bool) ($data['timing_belt_completed'] ?? false)) {
                $timingBeltDeadline = $maintenanceRecord->vehicle->deadlines()
                    ->where('type', Deadline::TYPE_CINGHIA)
                    ->where('is_renewed', false)
                    ->latest('due_date')
                    ->first();

                if ($timingBeltDeadline) {
                    $this->renewDeadline($maintenanceRecord, $timingBeltDeadline);
                } else {
                    // Nessuna scadenza da rinnovare (es. il tipo di
                    // distribuzione è stato impostato/corretto dopo la
                    // creazione del veicolo, senza passare dal prompt
                    // "crea scadenza cinghia"): prima il cambio veniva
                    // registrato ma non nasceva mai nessuna scadenza,
                    // lasciando il veicolo senza tracciamento cinghia.
                    $this->deadlineService->createTimingBeltDeadlineFromChange(
                        $maintenanceRecord->vehicle,
                        $maintenanceRecord->return_date ?? Carbon::today(),
                        $maintenanceRecord->mileage_at_service,
                    );
                }
            }

            // 5) Cambio gomme: monta le gomme collegate non ancora completate.
            $this->tireDispositionService->mountLinkedTires($maintenanceRecord, $data['previous_disposition'] ?? []);
        });
    }

    /**
     * Processa gli item marcati come completati in un appuntamento con data
     * di rientro: chiude i guasti, rinnova le scadenze e monta le gomme
     * collegate non ancora completate (stesso passo 5 di complete() — un
     * "Cambio Gomme" salvato da qui, con data di rientro già compilata,
     * passava per questo metodo invece che per complete() e le gomme
     * restavano "da montare al completamento" anche a intervento concluso).
     */
    public function processCompletedItems(MaintenanceRecord $maintenanceRecord, array $completedIssueIds, array $completedDeadlineIds, array $previousDisposition = []): void
    {
        // load(), non loadMissing(): chiamato sempre dopo che gli item sono
        // stati ricreati da zero (delete + re-create in
        // syncForExistingRecord()/createForNewRecord()), quindi una
        // relazione 'items' già in cache su questa stessa istanza — es.
        // caricata da UpdateMaintenanceRecordRequest per calcolare quali
        // gomme richiedono una disposizione, prima ancora che il
        // controller le ricreasse — andrebbe aggiornata qui, non
        // riutilizzata: altrimenti si opera sugli item vecchi (già
        // cancellati) e quelli nuovi restano "completed" a false.
        $maintenanceRecord->load(['items.itemable', 'vehicle.vehicleType']);

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
            ->map(fn ($item) => $item->itemable)
            ->filter();

        foreach ($completedDeadlines as $deadline) {
            if (in_array($deadline->type, [Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN, Deadline::TYPE_TAGLIANDO, Deadline::TYPE_CINGHIA], true)) {
                $this->renewDeadline($maintenanceRecord, $deadline);
            }
        }

        $this->tireDispositionService->mountLinkedTires($maintenanceRecord, $previousDisposition);
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
        $nextDueDate = match ($deadline->type) {
            Deadline::TYPE_MINISTERIAL => DeadlineRenewalCalculator::ministerial($baseDate, $maintenanceRecord->vehicle),
            Deadline::TYPE_OXYGEN => DeadlineRenewalCalculator::oxygen($baseDate, $maintenanceRecord->vehicle),
            default => null,
        };
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
        [$nextDueDate, $extra] = DeadlineRenewalCalculator::cinghia($baseDate, $vehicle, $maintenanceRecord->mileage_at_service);

        $this->deadlineService->createNextOccurrence($deadline, $vehicle, $nextDueDate, $extra);
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
        $baseDate = Carbon::parse($maintenanceRecord->return_date ?? Carbon::today());
        [$dueDate, $extra] = DeadlineRenewalCalculator::tagliando($baseDate, $maintenanceRecord->vehicle, $maintenanceRecord->mileage_at_service);

        $this->deadlineService->createNextOccurrence($deadline, $maintenanceRecord->vehicle, $dueDate, $extra);
    }
}
