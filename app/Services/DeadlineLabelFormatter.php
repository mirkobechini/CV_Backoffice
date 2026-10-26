<?php

namespace App\Services;

use App\Models\Deadline;
use Carbon\Carbon;

/**
 * Etichette "giorni/km mancanti" per i badge della colonna scadenze.
 * Estratto da Deadline per tenere il model sotto le 400 righe (audit
 * 2026-10-22, Q1) — stesso pattern già usato per
 * DeadlineDueDateCalculator/DeadlineStatusSyncService in questo file:
 * wrapper statici su Deadline con nome/firma invariati, per non toccare i
 * chiamanti esistenti.
 */
class DeadlineLabelFormatter
{
    /**
     * Etichetta dei giorni/km mancanti alla scadenza, per la colonna
     * "prossima scadenza" dell'index veicoli.
     */
    public static function daysLabel(Deadline $deadline): string
    {
        // Scadenze per km: mostra i km mancanti al target
        if ($deadline->interval_km !== null && $deadline->last_mileage !== null) {
            $deadline->loadMissing('vehicle.latestMileageLog');
            $currentMileage = $deadline->vehicle?->mileage;
            if ($currentMileage !== null) {
                $remaining = ($deadline->last_mileage + $deadline->interval_km) - $currentMileage;
                if ($remaining <= 0) {
                    return '+'.number_format(abs($remaining), 0, ',', '.').' km';
                }

                return number_format($remaining, 0, ',', '.').' km';
            }
        }

        // Scadenze per data: mostra i giorni alla scadenza
        if ($deadline->due_date) {
            return self::formatDaysRemaining($deadline->due_date);
        }

        return '—';
    }

    /**
     * Etichetta dei giorni mancanti/scaduti rispetto a due_date, indipendente
     * dal conteggio km. Usata per mostrare il badge "giorni" accanto a quello
     * "km" quando la scadenza ha entrambe le condizioni (es. Tagliando).
     */
    public static function dateRemainingLabel(Deadline $deadline): ?string
    {
        return $deadline->due_date ? self::formatDaysRemaining($deadline->due_date) : null;
    }

    /**
     * Etichetta dei km mancanti/superati rispetto al target, indipendente
     * dalla data. Usata per mostrare il badge "km" accanto a quello "giorni".
     */
    public static function kmRemainingLabel(Deadline $deadline): ?string
    {
        if ($deadline->interval_km === null || $deadline->last_mileage === null) {
            return null;
        }

        $deadline->loadMissing('vehicle.latestMileageLog');
        $currentMileage = $deadline->vehicle?->mileage;
        if ($currentMileage === null) {
            return null;
        }

        $remaining = ($deadline->last_mileage + $deadline->interval_km) - $currentMileage;
        $formatted = number_format(abs($remaining), 0, ',', '.');

        return $remaining <= 0 ? '+'.$formatted.' km' : $formatted.' km';
    }

    /**
     * "+N gg" se la data è già passata (scaduta da N giorni), "N gg"
     * altrimenti — condiviso tra daysLabel/dateRemainingLabel, che prima
     * duplicavano lo stesso calcolo (bug/DRY segnalato 2026-10-19).
     */
    private static function formatDaysRemaining(Carbon $date): string
    {
        $days = Carbon::today()->diffInDays($date, false);

        return $days < 0 ? '+'.abs($days).' gg' : $days.' gg';
    }
}
