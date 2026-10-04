<?php

namespace App\Services;

use App\Models\Deadline;
use Illuminate\Support\Carbon;

/**
 * Calcolo puro (nessuna query, nessuno stato) dello stato di una scadenza
 * dai suoi valori data/km. Estratto da Deadline per tenere il model sotto
 * le 400 righe.
 *
 * Due algoritmi distinti, DELIBERATAMENTE non unificati in questo refactor:
 * - calculateDisplayStatus(): usato dall'accessor automatic_status, tiene
 *   conto anche del preavviso al 90% del km target (isKmPending)
 * - calculateSyncedStatus(): usato da sync(Statuses)FromRules per il valore
 *   persistito nella colonna status — non ha il preavviso al 90% sul km.
 * Le due logiche divergevano già prima di questo refactor; unificarle
 * cambierebbe comportamento, quindi sono mantenute separate qui.
 */
class DeadlineStatusCalculator
{
    public static function calculateDisplayStatus(
        ?Carbon $dueDate,
        ?int $intervalKm,
        ?int $lastMileage,
        ?int $currentMileage,
        int $warningMonths,
    ): string {
        $today = Carbon::today();
        $isKmExpired = false;
        $isKmPending = false;

        if ($intervalKm !== null && $lastMileage !== null && $currentMileage !== null) {
            $thresholdKm = $lastMileage + $intervalKm;
            if ($currentMileage >= $thresholdKm) {
                $isKmExpired = true;
            }
            $warningKm = $lastMileage + (int) ($intervalKm * 0.9);
            if ($currentMileage >= $warningKm) {
                $isKmPending = true;
            }
        }

        if (! $dueDate && ! $intervalKm) {
            return Deadline::STATUS_PENDING;
        }

        $isDateExpired = $dueDate && $dueDate->isBefore($today);
        $isDatePending = false;
        if ($dueDate && ! $isDateExpired) {
            $warningStartDate = $dueDate->copy()->subMonthsNoOverflow($warningMonths);
            $isDatePending = $today->gte($warningStartDate);
        }

        if ($isKmExpired || $isDateExpired) {
            return Deadline::STATUS_EXPIRED;
        }

        if ($isKmPending || $isDatePending) {
            return Deadline::STATUS_PENDING;
        }

        return Deadline::STATUS_VALID;
    }

    public static function calculateSyncedStatus(
        ?Carbon $dueDate,
        ?int $intervalKm,
        ?int $lastMileage,
        ?int $currentMileage,
        int $warningMonths,
    ): string {
        $today = Carbon::today();
        $newStatus = null;

        if ($intervalKm !== null && $lastMileage !== null && $currentMileage !== null) {
            if ($currentMileage >= ($lastMileage + $intervalKm)) {
                $newStatus = Deadline::STATUS_EXPIRED;
            }
        }

        if ($newStatus === null && $dueDate) {
            $warningStartDate = $dueDate->copy()->subMonthsNoOverflow($warningMonths);

            if ($dueDate->isBefore($today)) {
                $newStatus = Deadline::STATUS_EXPIRED;
            } elseif ($dueDate->isAfter($today) && $dueDate->isAfter($today->copy()->addMonthsNoOverflow($warningMonths))) {
                $newStatus = Deadline::STATUS_VALID;
            } elseif ($today->gte($warningStartDate)) {
                $newStatus = Deadline::STATUS_PENDING;
            } else {
                $newStatus = Deadline::STATUS_VALID;
            }
        }

        return $newStatus ?? Deadline::STATUS_PENDING;
    }
}
