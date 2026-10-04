<?php

namespace App\Services;

use App\Models\Deadline;
use App\Models\Issue;
use App\Models\MaintenanceRecord;

/**
 * Collegamento/sincronizzazione di guasti e scadenze su un appuntamento
 * (store/update), e ripristino del loro stato quando l'appuntamento viene
 * eliminato. Estratto da MaintenanceRecordController: stessa logica usata
 * sia alla creazione che alla modifica.
 */
class MaintenanceRecordItemSyncService
{
    public function __construct(
        private readonly MaintenanceCompletionService $completionService,
    ) {
    }

    public function createForNewRecord(MaintenanceRecord $maintenanceRecord, array $data): void
    {
        $completedIssueIds = $data['completed_issue_ids'] ?? [];
        $completedDeadlineIds = $data['completed_deadline_ids'] ?? [];

        $this->attachIssues($maintenanceRecord, $data['issue_ids'] ?? [], $completedIssueIds);
        $this->attachDeadlines($maintenanceRecord, $data['deadline_ids'] ?? [], $completedDeadlineIds);
        $this->completionService->linkTireItems($maintenanceRecord, $data);

        // Se la data di rientro è compilata, l'appuntamento è considerato
        // completato: aggiorna i guasti e rinnova le scadenze marcate come
        // completate, partendo dalla data di rientro.
        if ($maintenanceRecord->return_date) {
            $this->completionService->processCompletedItems($maintenanceRecord, $completedIssueIds, $completedDeadlineIds);
        }
    }

    public function syncForExistingRecord(MaintenanceRecord $maintenanceRecord, array $data): void
    {
        // Prima di cancellare, registra i guasti attualmente collegati
        $oldIssueIds = $maintenanceRecord->items()
            ->where('itemable_type', Issue::class)
            ->pluck('itemable_id')
            ->toArray();

        $maintenanceRecord->items()->delete();

        $completedIssueIds = $data['completed_issue_ids'] ?? [];
        $completedDeadlineIds = $data['completed_deadline_ids'] ?? [];
        $newIssueIds = $data['issue_ids'] ?? [];

        $this->attachIssues($maintenanceRecord, $newIssueIds, $completedIssueIds);
        $this->reopenRemovedIssues($oldIssueIds, $newIssueIds);
        $this->attachDeadlines($maintenanceRecord, $data['deadline_ids'] ?? [], $completedDeadlineIds);
        $this->completionService->linkTireItems($maintenanceRecord, $data);

        if ($maintenanceRecord->return_date) {
            $this->completionService->processCompletedItems($maintenanceRecord, $completedIssueIds, $completedDeadlineIds);
        }
    }

    /**
     * Da chiamare prima di eliminare l'appuntamento: riapre i guasti ancora
     * in lavorazione e ripristina lo stato precedente delle scadenze
     * rinnovate da questo appuntamento (elimina la scadenza successiva
     * creata dal rinnovo, riporta quella originale a pending).
     *
     * @return string[] tipi di scadenza ripristinati, senza duplicati
     */
    public function restoreOnDelete(MaintenanceRecord $maintenanceRecord): array
    {
        $maintenanceRecord->loadMissing('items.itemable');

        // I guasti in lavorazione tornano in open (loop su modelli singoli,
        // non query di massa: whereIn()->update() bypassa gli Eloquent
        // event, quindi LogsActivity non registrerebbe questi cambi).
        $issueIds = $maintenanceRecord->items
            ->where('itemable_type', Issue::class)
            ->pluck('itemable_id');
        if ($issueIds->isNotEmpty()) {
            Issue::whereIn('id', $issueIds)
                ->where('status', 'in_progress')
                ->get()
                ->each(fn (Issue $issue) => $issue->update(['status' => 'open']));
        }

        $restoredDeadlines = [];
        $renewedDeadlines = $maintenanceRecord->items
            ->where('itemable_type', Deadline::class)
            ->map(fn ($item) => $item->itemable)
            ->filter()
            ->filter(fn ($d) => $d->is_renewed);

        foreach ($renewedDeadlines as $deadline) {
            // La scadenza successiva creata dal rinnovo è quella collegata
            // tramite renews_deadline_id (impostato uniformemente da
            // DeadlineService::createNextOccurrence per tutti i tipi,
            // cinghia inclusa).
            Deadline::where('renews_deadline_id', $deadline->id)->delete();

            $deadline->status = Deadline::STATUS_PENDING;
            $deadline->is_renewed = false;
            $deadline->save();

            $restoredDeadlines[] = $deadline->type;
        }

        return array_unique($restoredDeadlines);
    }

    private function attachIssues(MaintenanceRecord $maintenanceRecord, array $issueIds, array $completedIssueIds): void
    {
        foreach ($issueIds as $issueId) {
            $maintenanceRecord->items()->create([
                'itemable_id' => $issueId,
                'itemable_type' => Issue::class,
                'completed' => in_array((string) $issueId, $completedIssueIds, true),
            ]);
            // Il guasto passa automaticamente in lavorazione
            Issue::where('id', $issueId)->where('status', 'open')->update(['status' => 'in_progress']);
        }
    }

    private function attachDeadlines(MaintenanceRecord $maintenanceRecord, array $deadlineIds, array $completedDeadlineIds): void
    {
        foreach ($deadlineIds as $deadlineId) {
            $maintenanceRecord->items()->create([
                'itemable_id' => $deadlineId,
                'itemable_type' => Deadline::class,
                'completed' => in_array((string) $deadlineId, $completedDeadlineIds, true),
            ]);
        }
    }

    /**
     * I guasti rimossi dal form tornano in open (loop su modelli singoli,
     * stesso motivo di attachIssues/restoreOnDelete).
     */
    private function reopenRemovedIssues(array $oldIssueIds, array $newIssueIds): void
    {
        $removedIssueIds = array_diff($oldIssueIds, $newIssueIds);
        if (! empty($removedIssueIds)) {
            Issue::whereIn('id', $removedIssueIds)
                ->where('status', 'in_progress')
                ->get()
                ->each(fn (Issue $issue) => $issue->update(['status' => 'open']));
        }
    }
}
