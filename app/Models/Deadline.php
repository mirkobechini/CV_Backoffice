<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\Searchable;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;

class Deadline extends Model
{
    use SoftDeletes, LogsActivity, Searchable;

    /**
     * Cache dell'accessor automatic_status per evitare query ripetute
     * quando l'accessor viene chiamato più volte (status_color, status_label,
     * view, grouping) sullo stesso modello.
     */
    protected ?string $automaticStatusCache = null;

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }
    //status
    public const STATUS_PENDING = 'pending';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_RENEWED = 'renewed';
    public const STATUS_VALID = 'valid';
    //type
    public const TYPE_MINISTERIAL = 'Revisione Ministeriale';
    public const TYPE_OXYGEN = 'Revisione Impianto Ossigeno';
    public const TYPE_TAGLIANDO = 'Tagliando';
    public const TYPE_CINGHIA = 'Cinghia Distribuzione';
    public const OXYGEN_CHECK_INTERVAL_MONTHS = 12;
    public const TIMING_BELT_INTERVAL_DAYS = 3650; // 10 anni
    public const TIMING_BELT_INTERVAL_KM = 100000; // 100.000 km

    protected $fillable = [
        'vehicle_id',
        'type',
        'due_date',
        'status',
        'is_renewed',
        'interval_km',
        'last_mileage',
        'interval_days',
    ];

    protected $casts = [
        'due_date' => 'date',
        'is_renewed' => 'boolean',
        'interval_km' => 'integer',
        'last_mileage' => 'integer',
        'interval_days' => 'integer',
    ];

    protected $searchable = ['type', 'status'];

    public function scopeUpcoming(Builder $query, int $days = 30): Builder
    {
        return $query->where('due_date', '>=', Carbon::today())
            ->where('due_date', '<=', Carbon::today()->addDays($days))
            ->orderBy('due_date');
    }

    public function maintenanceRecordItems()
    {
        return $this->morphMany(MaintenanceRecordItem::class, 'itemable');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }



    public function getStatusColorAttribute(): string
    {
        return match ($this->automatic_status) {
            self::STATUS_EXPIRED => 'red',
            self::STATUS_PENDING => 'yellow',
            self::STATUS_RENEWED => 'green',
            self::STATUS_VALID => 'green',
            default => 'blue',
        };
    }

    public function getDueDateFormattedAttribute(): ?string
    {
        return $this->due_date?->format('m/Y');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->automatic_status) {
            self::STATUS_RENEWED => 'Rinnovata',
            self::STATUS_PENDING => 'In scadenza',
            self::STATUS_EXPIRED => 'Scaduta',
            self::STATUS_VALID => 'Valida',
            default => 'Sconosciuto',
        };
    }

    public function getAutomaticStatusAttribute(): string
    {
        // Cachea il risultato: l'accessor può essere chiamato più volte
        // (status_color, status_label, view, grouping) e il calcolo può
        // comportare query sul veicolo/km.
        if ($this->automaticStatusCache !== null) {
            return $this->automaticStatusCache;
        }

        $warningMonths = max(0, (int) config('deadlines.warning_months', 3));

        // Se marcata manualmente come rinnovata, preserviamo quel valore.
        if ($this->is_renewed) {
            return $this->automaticStatusCache = self::STATUS_RENEWED;
        }

        $today = Carbon::today();
        $isKmExpired = false;
        $isKmPending = false;

        // Check km-based conditions (carica veicolo e ultimo km on-demand se necessario)
        if ($this->interval_km !== null && $this->last_mileage !== null) {
            $this->loadMissing('vehicle.latestMileageLog');
            if ($this->vehicle) {
                $currentMileage = $this->vehicle->mileage;
                if ($currentMileage !== null) {
                    $thresholdKm = $this->last_mileage + $this->interval_km;
                    if ($currentMileage >= $thresholdKm) {
                        $isKmExpired = true;
                    }
                    $warningKm = $this->last_mileage + (int) ($this->interval_km * 0.9);
                    if ($currentMileage >= $warningKm) {
                        $isKmPending = true;
                    }
                }
            }
        }

        // Se non c'è né data né km, pending
        if (!$this->due_date && !$this->interval_km) {
            return $this->automaticStatusCache = self::STATUS_PENDING;
        }

        // Check date-based expiry
        $isDateExpired = $this->due_date && $this->due_date->isBefore($today);
        $isDatePending = false;
        if ($this->due_date && !$isDateExpired) {
            $warningStartDate = $this->due_date->copy()->subMonthsNoOverflow($warningMonths);
            $isDatePending = $today->gte($warningStartDate);
        }

        // Expired se UNA delle condizioni è scaduta (km O data — il primo che arriva)
        if ($isKmExpired || $isDateExpired) {
            return $this->automaticStatusCache = self::STATUS_EXPIRED;
        }

        // Pending se UNA delle condizioni è in warning
        if ($isKmPending || $isDatePending) {
            return $this->automaticStatusCache = self::STATUS_PENDING;
        }

        return $this->automaticStatusCache = self::STATUS_VALID;
    }

    /**
     * Sincronizza lo stato di più scadenze in un'unica passata.
     *
     * Pre-carica le relazioni necessarie una sola volta e raggruppa gli
     * aggiornamenti di stato in un unico UPDATE bulk, evitando N query di
     * loadMissing + N query di save() per ogni scadenza.
     *
     * @param  \Illuminate\Support\Collection<int, Deadline>|iterable  $deadlines
     */
    public static function syncStatusesFromRules(iterable $deadlines): void
    {
        // Mantieni il tipo originale: Eloquent\Collection ha loadMissing/load,
        // una Collection generica no. Se non è una collection Eloquent,
        // la convertiamo in una per poter usare l'eager loading in una sola query.
        if (!$deadlines instanceof \Illuminate\Database\Eloquent\Collection) {
            $deadlines = new \Illuminate\Database\Eloquent\Collection($deadlines);
        }

        if ($deadlines->isEmpty()) {
            return;
        }

        // Pre-carica veicolo + ultimo km per tutte le scadenze in una sola query.
        $deadlines->loadMissing('vehicle.latestMileageLog');

        $today = Carbon::today();
        $warningMonths = max(0, (int) config('deadlines.warning_months', 3));

        $updates = [];

        foreach ($deadlines as $deadline) {
            // Le scadenze marcate manualmente come rinnovate restano invariate.
            if ($deadline->is_renewed) {
                continue;
            }

            $newStatus = null;

            // KM-based check
            if ($deadline->interval_km !== null && $deadline->last_mileage !== null) {
                $currentMileage = $deadline->vehicle?->mileage;
                if ($currentMileage !== null && $currentMileage >= ($deadline->last_mileage + $deadline->interval_km)) {
                    $newStatus = self::STATUS_EXPIRED;
                }
            }

            // Date-based check (solo se non è già expired per km)
            if ($newStatus === null && $deadline->due_date) {
                $warningStartDate = $deadline->due_date->copy()->subMonthsNoOverflow($warningMonths);

                if ($deadline->due_date->isBefore($today)) {
                    $newStatus = self::STATUS_EXPIRED;
                } elseif ($deadline->due_date->isAfter($today) && $deadline->due_date->isAfter($today->copy()->addMonthsNoOverflow($warningMonths))) {
                    $newStatus = self::STATUS_VALID;
                } elseif ($today->gte($warningStartDate)) {
                    $newStatus = self::STATUS_PENDING;
                } else {
                    $newStatus = self::STATUS_VALID;
                }
            }

            // Se non c'è né data né km, pending
            if ($newStatus === null) {
                $newStatus = self::STATUS_PENDING;
            }

            if ($deadline->status !== $newStatus) {
                $updates[$deadline->id] = $newStatus;
            }
        }

        if (!empty($updates)) {
            // Raggruppa per stato e aggiorna in blocco: al massimo 4 query
            // UPDATE (una per stato) invece di N query save().
            $grouped = [];
            foreach ($updates as $id => $status) {
                $grouped[$status][] = $id;
            }

            foreach ($grouped as $status => $ids) {
                self::query()
                    ->whereIn('id', $ids)
                    ->update(['status' => $status]);
            }
        }
    }

    public function syncStatusFromRules(): void
    {
        // Sincronizza lo stato persistito con le regole temporali/km correnti.
        if ($this->is_renewed) {
            return;
        }

        $today = Carbon::today();
        $warningMonths = max(0, (int) config('deadlines.warning_months', 3));
        $newStatus = null;

        // KM-based check
        if ($this->interval_km !== null && $this->last_mileage !== null) {
            $this->loadMissing('vehicle.latestMileageLog');
            $currentMileage = $this->vehicle?->mileage;
            if ($currentMileage !== null && $currentMileage >= ($this->last_mileage + $this->interval_km)) {
                $newStatus = self::STATUS_EXPIRED;
            }
        }

        // Date-based check (solo se non è già expired per km)
        if ($newStatus === null && $this->due_date) {
            $warningStartDate = $this->due_date->copy()->subMonthsNoOverflow($warningMonths);

            if ($this->due_date->isBefore($today)) {
                $newStatus = self::STATUS_EXPIRED;
            } elseif ($this->due_date->isAfter($today) && $this->due_date->isAfter($today->copy()->addMonthsNoOverflow($warningMonths))) {
                $newStatus = self::STATUS_VALID;
            } elseif ($today->gte($warningStartDate)) {
                $newStatus = self::STATUS_PENDING;
            } else {
                $newStatus = self::STATUS_VALID;
            }
        }

        // Se non c'è né data né km, pending
        if ($newStatus === null) {
            $newStatus = self::STATUS_PENDING;
        }

        if ($this->status !== $newStatus) {
            $this->status = $newStatus;
            $this->save();
        }
    }

    public static function calculateMinisterialDueDateForVehicle(Vehicle $vehicle, ?int $excludeDeadlineId = null): ?Carbon
    {
        if (!$vehicle->immatricolation_date || !$vehicle->vehicleType) {
            return null;
        }

        $query = $vehicle->deadlines()
            ->where('type', self::TYPE_MINISTERIAL)
            ->where('status', self::STATUS_RENEWED)
            ->orderByDesc('due_date');

        if ($excludeDeadlineId !== null) {
            $query->where('id', '!=', $excludeDeadlineId);
        }

        $lastRenewedDeadline = $query->first();

        // Se c'è una revisione rinnovata precedente, calcoliamo la successiva da quella;
        // altrimenti partiamo dalla data di immatricolazione con intervallo iniziale.
        if ($lastRenewedDeadline && $lastRenewedDeadline->due_date) {
            $monthsToAdd = (int) $vehicle->vehicleType->regular_inspection_months;
            return Carbon::parse($lastRenewedDeadline->due_date)->addMonthsNoOverflow($monthsToAdd);
        }

        $monthsToAdd = (int) $vehicle->vehicleType->first_inspection_months;
        return Carbon::parse($vehicle->immatricolation_date)->addMonthsNoOverflow($monthsToAdd);
    }

    public static function calculateOxygenDueDateForVehicle(Vehicle $vehicle, ?int $excludeDeadlineId = null): ?Carbon
    {
        if (!$vehicle->immatricolation_date || !self::supportsOxygenCheckForVehicle($vehicle)) {
            return null;
        }

        $query = $vehicle->deadlines()
            ->where('type', self::TYPE_OXYGEN)
            ->where('status', self::STATUS_RENEWED)
            ->orderByDesc('due_date');

        if ($excludeDeadlineId !== null) {
            $query->where('id', '!=', $excludeDeadlineId);
        }

        $lastRenewedDeadline = $query->first();

        if ($lastRenewedDeadline && $lastRenewedDeadline->due_date) {
            return Carbon::parse($lastRenewedDeadline->due_date)
                ->addMonthsNoOverflow(self::OXYGEN_CHECK_INTERVAL_MONTHS);
        }

        return Carbon::parse($vehicle->immatricolation_date)
            ->addMonthsNoOverflow(self::OXYGEN_CHECK_INTERVAL_MONTHS);
    }

    public static function supportsOxygenCheckForVehicle(Vehicle $vehicle): bool
    {
        return (bool) optional($vehicle->vehicleType)->needs_oxygen_check;
    }
}
