<?php

namespace App\Services;

use App\Models\Deadline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Statistiche scadenze per la tab "Scadenze" di /admin/statistics (solo
 * capi/sottocapi). Scoperte per gruppo tramite Deadline::forCurrentUser().
 */
class DeadlineStatisticsService
{
    /**
     * Scadenze non rinnovate per stato attuale (scaduta/in scadenza/
     * valida/sconosciuta — automatic_status, stesso calcolo di status_label/
     * status_color): sempre "adesso", non filtrato per periodo — lo stato
     * di una scadenza dipende dalla data odierna, non è un dato storico
     * ricostruibile per un mese passato.
     *
     * @return Collection<int, array{label: string, color: string, count: int}>
     */
    public function countByStatus(): Collection
    {
        $deadlines = Deadline::forCurrentUser()
            ->where('is_renewed', false)
            ->with('vehicle.latestMileageLog')
            ->get();

        return $deadlines
            ->groupBy(fn (Deadline $d) => $d->status_label)
            ->map(fn ($group) => [
                'label' => $group->first()->status_label,
                'color' => $group->first()->status_color,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values();
    }

    /**
     * Scadenze oggi scadute, per tipo (Assicurazione, Tagliando, ecc.):
     * quale tipologia resta più spesso indietro.
     *
     * @return Collection<string, int> tipo => conteggio
     */
    public function expiredCountByType(): Collection
    {
        return Deadline::forCurrentUser()
            ->where('is_renewed', false)
            ->with('vehicle.latestMileageLog')
            ->get()
            ->filter(fn (Deadline $d) => $d->automatic_status === Deadline::STATUS_EXPIRED)
            ->countBy('type')
            ->sortDesc();
    }

    /**
     * Costo medio di rinnovo assicurazione (somma delle coperture
     * collegate, Deadline::insurance_premium_total) tra le scadenze di
     * tipo Assicurazione con scadenza nel periodo e almeno una copertura
     * con un costo registrato — quelle senza coperture non abbassano
     * artificialmente la media, semplicemente non hanno ancora il dato.
     */
    public function averageInsuranceRenewalCost(?Carbon $since = null): ?float
    {
        $deadlines = Deadline::forCurrentUser()
            ->where('type', Deadline::TYPE_ASSICURAZIONE)
            ->when($since, fn ($q) => $q->where('due_date', '>=', $since))
            ->with('insuranceCoverages')
            ->get()
            ->filter(fn (Deadline $d) => $d->insuranceCoverages->isNotEmpty());

        if ($deadlines->isEmpty()) {
            return null;
        }

        return round($deadlines->avg(fn (Deadline $d) => $d->insurance_premium_total), 2);
    }
}
