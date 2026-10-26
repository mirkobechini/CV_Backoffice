<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\MaintenanceRecordItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Statistiche sui guasti per parte del veicolo (Issue::part), per la
 * pagina /admin/statistics (solo capi/sottocapi). Tutte le query sono
 * scoperte per gruppo tramite Vehicle::forCurrentUser() sulla relazione.
 */
class IssueStatisticsService
{
    private function baseQuery(?Carbon $since)
    {
        return Issue::forCurrentUser()
            ->whereNotNull('part')
            ->when($since, fn ($q) => $q->where('event_date', '>=', $since));
    }

    /**
     * Quante volte ogni parte compare tra i guasti, ordinato dal più
     * frequente. Conta tutti i guasti indipendentemente dallo stato: la
     * domanda è "quale parte si guasta più spesso", non "quanti sono
     * ancora aperti".
     *
     * @return Collection<string, int> parte => conteggio
     */
    public function countsByPart(?Carbon $since = null): Collection
    {
        return $this->baseQuery($since)
            ->get(['part'])
            ->countBy('part')
            ->sortDesc();
    }

    /**
     * Matrice veicolo × parte: quante volte ciascun veicolo ha avuto un
     * guasto a ciascuna parte. Utile per capire se un mezzo specifico ha
     * un problema ricorrente su un componente, invece del solo totale di
     * flotta.
     *
     * Righe tenute per vehicle_id, non per internal_code: la sigla non è
     * univoca (solo il formato a 4 cifre è vincolato), quindi due veicoli
     * diversi con la stessa sigla (es. un mezzo dismesso sostituito con
     * lo stesso numero di flotta) andrebbero altrimenti sommati sotto
     * un'unica riga, mescolando i guasti di due mezzi diversi.
     *
     * @return array{vehicles: array<int, array{id: int, label: string}>, parts: array<int, string>, matrix: array<int, array<string, int>>}
     */
    public function vehiclePartMatrix(?Carbon $since = null): array
    {
        $issues = $this->baseQuery($since)->with('vehicle')->get();

        $matrix = [];
        $vehicleLabels = [];

        foreach ($issues as $issue) {
            $vehicleId = $issue->vehicle_id;
            $vehicleLabels[$vehicleId] = $issue->vehicle?->internal_code ?? 'N/A';
            $matrix[$vehicleId][$issue->part] = ($matrix[$vehicleId][$issue->part] ?? 0) + 1;
        }

        $parts = $issues->pluck('part')->unique()->sort()->values()->all();

        $vehicles = collect($vehicleLabels)
            ->map(fn ($label, $id) => ['id' => $id, 'label' => $label])
            ->sortBy('label')
            ->values()
            ->all();

        return compact('vehicles', 'parts', 'matrix');
    }

    /**
     * Conteggio guasti per mese (ultimi 12 mesi da oggi, o da $since se più
     * recente), per vedere se un certo tipo di problema sta aumentando.
     *
     * @return Collection<string, int> "mese/anno" => conteggio, in ordine cronologico
     */
    public function monthlyTrend(?Carbon $since = null): Collection
    {
        $from = Carbon::today()->subMonths(11)->startOfMonth();

        if ($since && $since->gt($from)) {
            $from = $since->copy()->startOfMonth();
        }

        $issues = $this->baseQuery($since)
            ->where('event_date', '>=', $from)
            ->get(['event_date']);

        $counts = $issues->countBy(fn (Issue $issue) => $issue->event_date->format('Y-m'));

        // Mesi senza guasti vanno comunque mostrati a zero, altrimenti
        // l'andamento sembra "a scatti" invece che continuo.
        $months = collect();
        $cursor = $from->copy();
        $end = Carbon::today()->startOfMonth();

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $months[$key] = $counts->get($key, 0);
            $cursor->addMonth();
        }

        return $months;
    }

    /**
     * Costo totale di riparazione per parte: quando un appuntamento copre
     * più guasti insieme, il suo costo viene diviso in parti uguali tra
     * quei guasti (nessun doppio conteggio, stima onesta anche se non
     * perfettamente precisa — l'alternativa, costo pieno a ciascuno,
     * gonfierebbe il totale complessivo).
     *
     * @return array<string, array{cost: float, count: int}> parte => totale costo e numero di guasti con costo registrato
     */
    public function costByPart(?Carbon $since = null): array
    {
        $issues = $this->baseQuery($since)
            ->with('maintenanceRecordItems.maintenanceRecord')
            ->get();

        $recordIds = $issues
            ->flatMap(fn (Issue $issue) => $issue->maintenanceRecordItems)
            ->pluck('maintenance_record_id')
            ->unique();

        // Quanti guasti condividono ciascun appuntamento, per dividere il
        // costo in parti uguali — una sola query invece di una per item.
        $issueItemCountsByRecord = MaintenanceRecordItem::whereIn('maintenance_record_id', $recordIds)
            ->where('itemable_type', Issue::class)
            ->get(['maintenance_record_id'])
            ->countBy('maintenance_record_id');

        $result = [];

        foreach ($issues as $issue) {
            foreach ($issue->maintenanceRecordItems as $item) {
                $record = $item->maintenanceRecord;

                if (! $record || $record->cost === null) {
                    continue;
                }

                $shareCount = $issueItemCountsByRecord->get($record->id, 1);
                $share = (float) $record->cost / max($shareCount, 1);

                $result[$issue->part]['cost'] = ($result[$issue->part]['cost'] ?? 0) + $share;
                $result[$issue->part]['count'] = ($result[$issue->part]['count'] ?? 0) + 1;
            }
        }

        uasort($result, fn ($a, $b) => $b['cost'] <=> $a['cost']);

        return $result;
    }
}
