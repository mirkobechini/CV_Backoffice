<?php

namespace App\Services;

use App\Models\Deadline;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\Tire;
use App\Models\Vehicle;

/**
 * Dati di supporto per i form create/edit di MaintenanceRecordController:
 * estratti dal controller perché identici nelle due viste a parte i
 * collegamenti già esistenti da preservare in edit.
 */
class MaintenanceRecordFormDataService
{
    public function forCreate(): array
    {
        return [
            // latestMileageLog eager-caricato: serve per mostrare l'ultimo km noto
            // come placeholder/suggerimento nel campo "Chilometraggio all'appuntamento".
            'vehicles' => Vehicle::with('latestMileageLog')->forCurrentUser()->get(),
            'providers' => Provider::all(),
            // Guasti aperti o in lavorazione: selezionabili per nuovi appuntamenti.
            'openIssues' => Issue::whereIn('status', ['open', 'in_progress'])->get(['id', 'vehicle_id', 'description', 'event_date']),
            // Guasti risolti non ancora collegati: per registrare riparazioni già avvenute.
            'closedIssues' => Issue::where('status', 'closed')
                ->whereDoesntHave('maintenanceRecordItems')
                ->get(['id', 'vehicle_id', 'description', 'event_date']),
            'pendingDeadlines' => $this->pendingDeadlines(),
            'storedTires' => Tire::where('status', Tire::STATUS_STORED)
                ->whereHas('vehicle', fn ($q) => $q->forCurrentUser())
                ->get(['id', 'vehicle_id', 'season', 'position', 'brand', 'model_name', 'size']),
        ];
    }

    public function forEdit(MaintenanceRecord $maintenanceRecord): array
    {
        $linkedIssueIds = $maintenanceRecord->items
            ->where('itemable_type', Issue::class)
            ->pluck('itemable_id');

        $linkedDeadlineIds = $maintenanceRecord->items
            ->where('itemable_type', Deadline::class)
            ->pluck('itemable_id');

        $linkedTireIds = $maintenanceRecord->items
            ->where('itemable_type', Tire::class)
            ->pluck('itemable_id');

        return [
            'vehicles' => Vehicle::with('latestMileageLog')->forCurrentUser()->get(),
            'providers' => Provider::all(),
            // In edit rendiamo selezionabili i guasti attivi + quelli già collegati al record.
            'openIssues' => Issue::whereIn('status', ['open'])
                ->orWhereIn('id', $linkedIssueIds)
                ->get(['id', 'vehicle_id', 'description', 'status', 'event_date']),
            'closedIssues' => Issue::where('status', 'closed')
                ->whereDoesntHave('maintenanceRecordItems')
                ->get(['id', 'vehicle_id', 'description', 'event_date']),
            'pendingDeadlines' => $this->pendingDeadlines($linkedDeadlineIds),
            // Set collegati (se presenti) + gli altri set in magazzino del
            // veicolo, per poterli mantenere selezionati in modifica.
            'storedTires' => Tire::where(function ($q) use ($linkedTireIds) {
                $q->where('status', Tire::STATUS_STORED);
                if ($linkedTireIds->isNotEmpty()) {
                    $q->orWhereIn('id', $linkedTireIds);
                }
            })
                ->whereHas('vehicle', fn ($q) => $q->forCurrentUser())
                ->get(['id', 'vehicle_id', 'season', 'position', 'brand', 'model_name', 'size']),
            'linkedTireIds' => $linkedTireIds,
        ];
    }

    /**
     * Una sola deadline per tipo per veicolo: prendiamo l'ultima non
     * rinnovata, mantenendo quelle già collegate al record in edit.
     */
    private function pendingDeadlines($linkedDeadlineIds = null)
    {
        // vehicle.latestMileageLog eager-caricato: days_label/status_label
        // (mostrati nel picker) lo caricherebbero altrimenti una alla volta.
        $query = Deadline::with('vehicle.latestMileageLog')
            ->whereIn('status', ['pending', 'expired', 'valid']);

        if ($linkedDeadlineIds !== null) {
            $query->orWhereIn('id', $linkedDeadlineIds);
        }

        return $query->orderByDesc('due_date')
            ->get()
            ->unique(fn ($item) => $item->vehicle_id . '-' . $item->type)
            ->values();
    }
}
