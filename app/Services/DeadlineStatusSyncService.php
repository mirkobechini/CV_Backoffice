<?php

namespace App\Services;

use App\Models\Deadline;
use Illuminate\Support\Collection;

/**
 * Sincronizza la colonna status persistita con le regole temporali/km
 * correnti (calcolate da DeadlineStatusCalculator, puro calcolo senza
 * scritture). Estratto da Deadline per tenere il model sotto le 400 righe
 * (audit 2026-10-19, C12) — stesso pattern già usato per
 * DeadlineDueDateCalculator/DeadlineStatusCalculator in questo file:
 * wrapper statici su Deadline con nome/firma invariati, per non toccare i
 * chiamanti esistenti (DeadlineController, DashboardController,
 * VehicleObserver, ...).
 */
class DeadlineStatusSyncService
{
    /**
     * Sincronizza più scadenze in un'unica passata: pre-carica le
     * relazioni necessarie una sola volta e raggruppa gli aggiornamenti in
     * un unico UPDATE bulk per stato, evitando N query di loadMissing +
     * N query di save() per ogni scadenza.
     *
     * @param  Collection<int, Deadline>|iterable  $deadlines
     */
    public static function syncMany(iterable $deadlines): void
    {
        // Mantieni il tipo originale: Eloquent\Collection ha loadMissing/load,
        // una Collection generica no. Se non è una collection Eloquent,
        // la convertiamo in una per poter usare l'eager loading in una sola query.
        if (! $deadlines instanceof \Illuminate\Database\Eloquent\Collection) {
            $deadlines = new \Illuminate\Database\Eloquent\Collection($deadlines);
        }

        if ($deadlines->isEmpty()) {
            return;
        }

        // Pre-carica veicolo + ultimo km per tutte le scadenze in una sola query.
        $deadlines->loadMissing('vehicle.latestMileageLog');

        $warningMonths = max(0, (int) config('deadlines.warning_months', 3));

        $updates = [];

        foreach ($deadlines as $deadline) {
            // Le scadenze marcate manualmente come rinnovate restano invariate.
            if ($deadline->is_renewed) {
                continue;
            }

            $newStatus = DeadlineStatusCalculator::calculateSyncedStatus(
                $deadline->due_date,
                $deadline->interval_km,
                $deadline->last_mileage,
                $deadline->vehicle?->mileage,
                $warningMonths,
            );

            if ($deadline->status !== $newStatus) {
                $updates[$deadline->id] = $newStatus;
            }
        }

        if (empty($updates)) {
            return;
        }

        // Raggruppa per stato e aggiorna in blocco: al massimo 4 query
        // UPDATE (una per stato) invece di N query save().
        $grouped = [];
        foreach ($updates as $id => $status) {
            $grouped[$status][] = $id;
        }

        foreach ($grouped as $status => $ids) {
            Deadline::query()
                ->whereIn('id', $ids)
                ->update(['status' => $status]);
        }
    }

    public static function syncOne(Deadline $deadline): void
    {
        if ($deadline->is_renewed) {
            return;
        }

        $currentMileage = null;
        if ($deadline->interval_km !== null && $deadline->last_mileage !== null) {
            $deadline->loadMissing('vehicle.latestMileageLog');
            $currentMileage = $deadline->vehicle?->mileage;
        }

        $newStatus = DeadlineStatusCalculator::calculateSyncedStatus(
            $deadline->due_date,
            $deadline->interval_km,
            $deadline->last_mileage,
            $currentMileage,
            max(0, (int) config('deadlines.warning_months', 3)),
        );

        if ($deadline->status !== $newStatus) {
            $deadline->status = $newStatus;
            $deadline->save();
        }
    }
}
