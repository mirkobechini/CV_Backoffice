<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\EquipmentIssue;
use App\Models\EquipmentMaintenanceRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Statistiche attrezzature (guasti + costi) per la tab "Attrezzature" di
 * /admin/statistics (solo capi/sottocapi). Le attrezzature non assegnate a
 * un veicolo sono condivise tra gruppi (stesso comportamento permissivo già
 * usato in EquipmentIssueController/EquipmentMaintenanceRecordController):
 * visibili a chiunque, non solo al gruppo che le ha in mano in quel momento.
 */
class EquipmentStatisticsService
{
    private function issueBaseQuery(?Carbon $since)
    {
        return EquipmentIssue::whereHas('equipment', function ($q) {
            $q->whereDoesntHave('vehicle')->orWhereHas('vehicle', fn ($vq) => $vq->forCurrentUser());
        })->when($since, fn ($q) => $q->where('event_date', '>=', $since));
    }

    private function maintenanceBaseQuery(?Carbon $since)
    {
        return EquipmentMaintenanceRecord::whereHas('equipments', function ($q) {
            $q->whereDoesntHave('vehicle')->orWhereHas('vehicle', fn ($vq) => $vq->forCurrentUser());
        })
            ->whereNotNull('cost')
            ->when($since, fn ($q) => $q->where('appointment_date', '>=', $since));
    }

    /**
     * Guasti per attrezzatura nel periodo, le 10 più problematiche (stesso
     * limite/criterio di FleetUsageStatisticsService::issueCountByVehicle()).
     *
     * @return Collection<int, array{equipment_id: int, label: string, count: int}>
     */
    public function issueCountByEquipment(?Carbon $since = null): Collection
    {
        $counts = $this->issueBaseQuery($since)->get(['equipment_id'])->countBy('equipment_id');
        $labels = Equipment::whereIn('id', $counts->keys())->pluck('name', 'id');

        return $counts
            ->map(fn ($count, $equipmentId) => [
                'equipment_id' => $equipmentId,
                'label' => $labels->get($equipmentId, 'N/A'),
                'count' => $count,
            ])
            ->sortByDesc('count')
            ->take(10)
            ->values();
    }

    /**
     * Guasti per tipo di attrezzatura, per vedere se una categoria (es.
     * estintori) si guasta più delle altre.
     *
     * @return Collection<string, int> tipo => conteggio
     */
    public function issueCountByType(?Carbon $since = null): Collection
    {
        return $this->issueBaseQuery($since)
            ->with('equipment.equipmentType')
            ->get()
            ->countBy(fn (EquipmentIssue $issue) => $issue->equipment?->equipmentType?->name ?? 'N/A')
            ->sortDesc();
    }

    /**
     * Spesa totale manutenzione attrezzature per anno (anno
     * dell'appuntamento).
     *
     * @return Collection<string, float>
     */
    public function costByYear(?Carbon $since = null): Collection
    {
        return $this->maintenanceBaseQuery($since)
            ->get(['appointment_date', 'cost'])
            ->groupBy(fn (EquipmentMaintenanceRecord $r) => $r->appointment_date->format('Y'))
            ->map(fn ($group) => (float) $group->sum('cost'))
            ->sortKeys();
    }

    /**
     * Spesa totale manutenzione per attrezzatura nel periodo: quando un
     * appuntamento coinvolge più attrezzature insieme, il costo viene
     * diviso in parti uguali tra loro (stesso approccio di
     * IssueStatisticsService::costByPart() — nessun doppio conteggio).
     *
     * @return Collection<int, array{equipment_id: int, label: string, cost: float}>
     */
    public function costByEquipment(?Carbon $since = null): Collection
    {
        $records = $this->maintenanceBaseQuery($since)->with('equipments')->get();
        $totals = collect();

        foreach ($records as $record) {
            $equipmentCount = $record->equipments->count();
            if ($equipmentCount === 0) {
                continue;
            }

            $share = (float) $record->cost / $equipmentCount;

            foreach ($record->equipments as $equipment) {
                $totals[$equipment->id] = ($totals[$equipment->id] ?? 0) + $share;
            }
        }

        $labels = Equipment::whereIn('id', $totals->keys())->pluck('name', 'id');

        return $totals
            ->map(fn ($cost, $equipmentId) => [
                'equipment_id' => $equipmentId,
                'label' => $labels->get($equipmentId, 'N/A'),
                'cost' => $cost,
            ])
            ->sortByDesc('cost')
            ->values();
    }
}
