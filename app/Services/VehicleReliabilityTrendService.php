<?php

namespace App\Services;

use App\Models\MaintenanceRecord;
use App\Models\Vehicle;

/**
 * Tendenza di affidabilità di un veicolo sulle riparazioni NON
 * programmate (activity_type = Riparazione). Deliberatamente non una
 * "previsione del prossimo guasto": un guasto non programmato non ha un
 * intervallo fisso per definizione (a differenza di tagliando/revisione/
 * cinghia, già calcolati esattamente da Deadline), quindi qui si mostra
 * solo se il ritmo delle riparazioni sta accelerando rispetto allo
 * storico del veicolo stesso — un segnale di attenzione, non una data.
 *
 * Vedi docs/ROADMAP_IDEE.md §6 per la discussione che ha portato a questa
 * versione (ridotta rispetto all'idea originale "per tipo di componente",
 * non realizzabile senza un campo categoria su Issue che oggi non esiste).
 */
class VehicleReliabilityTrendService
{
    /**
     * Sotto questa soglia di riparazioni storiche non c'è abbastanza
     * dato nemmeno per una media grezza: meglio non mostrare nulla che
     * mostrare un numero costruito su 1-2 punti.
     */
    private const MIN_REPAIRS_FOR_AVERAGE = 3;

    /**
     * Sotto questa soglia non c'è un "prima" con cui confrontare l'ultimo
     * intervallo: il confronto richiede almeno un intervallo precedente
     * oltre a quello più recente.
     */
    private const MIN_REPAIRS_FOR_TREND = 4;

    /**
     * L'ultimo intervallo viene segnalato come "in peggioramento" solo se
     * è una frazione sostanzialmente più corta della media precedente
     * (qui: meno del 60% — le riparazioni stanno avvenendo quasi il
     * doppio più spesso), non per qualunque variazione minore che
     * sarebbe solo rumore statistico su pochi punti.
     */
    private const WORSENING_RATIO_THRESHOLD = 0.6;

    /**
     * @return array{repairs_count:int, average_interval_days:int, last_interval_days:int, is_worsening:bool}|null
     *         null se non c'è nemmeno abbastanza storico per una media.
     */
    public function analyze(Vehicle $vehicle): ?array
    {
        $dates = MaintenanceRecord::where('vehicle_id', $vehicle->id)
            ->where('activity_type', MaintenanceRecord::ACTIVITY_REPAIR)
            ->whereNotNull('appointment_date')
            ->orderBy('appointment_date')
            ->pluck('appointment_date');

        if ($dates->count() < self::MIN_REPAIRS_FOR_AVERAGE) {
            return null;
        }

        $intervals = [];
        for ($i = 1; $i < $dates->count(); $i++) {
            $intervals[] = $dates[$i - 1]->diffInDays($dates[$i]);
        }

        $averageIntervalDays = (int) round(array_sum($intervals) / count($intervals));
        $lastIntervalDays = (int) end($intervals);

        $result = [
            'repairs_count' => $dates->count(),
            'average_interval_days' => $averageIntervalDays,
            'last_interval_days' => $lastIntervalDays,
            'is_worsening' => false,
        ];

        if (count($intervals) >= self::MIN_REPAIRS_FOR_TREND - 1) {
            $precedingIntervals = array_slice($intervals, 0, -1);
            $precedingAverage = array_sum($precedingIntervals) / count($precedingIntervals);

            $result['is_worsening'] = $precedingAverage > 0
                && $lastIntervalDays <= $precedingAverage * self::WORSENING_RATIO_THRESHOLD;
        }

        return $result;
    }
}
