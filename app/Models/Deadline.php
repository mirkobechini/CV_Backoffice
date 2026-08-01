<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\Searchable;
use Spatie\Activitylog\Traits\LogsActivity;

class Deadline extends Model
{
    use SoftDeletes, LogsActivity, Searchable;

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
        $warningMonths = max(0, (int) config('deadlines.warning_months', 3));

        // Se marcata manualmente come rinnovata, preserviamo quel valore.
        if ($this->is_renewed) {
            return self::STATUS_RENEWED;
        }

        $today = Carbon::today();
        $isKmExpired = false;
        $isKmPending = false;

        // Check km-based conditions (solo se la relazione vehicle è già caricata)
        if ($this->interval_km !== null && $this->last_mileage !== null && $this->relationLoaded('vehicle') && $this->vehicle) {
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

        // Se non c'è né data né km, pending
        if (!$this->due_date && !$this->interval_km) {
            return self::STATUS_PENDING;
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
            return self::STATUS_EXPIRED;
        }

        // Pending se UNA delle condizioni è in warning
        if ($isKmPending || $isDatePending) {
            return self::STATUS_PENDING;
        }

        return self::STATUS_VALID;
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
            $this->loadMissing('vehicle');
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
