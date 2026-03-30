<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Deadline extends Model
{
    //status
    public const STATUS_PENDING = 'pending';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_RENEWED = 'renewed';
    public const STATUS_VALID = 'valid';
    //type
    public const TYPE_MINISTERIAL = 'Revisione Ministeriale';
    public const TYPE_OXYGEN = 'Revisione Impianto Ossigeno';
    public const OXYGEN_CHECK_INTERVAL_MONTHS = 12;

    protected $fillable = [
        'vehicle_id',
        'type',
        'due_date',
        'status',
        'is_renewed',
    ];

    protected $casts = [
        'due_date' => 'date',
        'is_renewed' => 'boolean',
    ];


    public function maintenanceRecord()
    {
        return $this->hasOne(MaintenanceRecord::class);
    }




    public function getDueDateFormattedAttribute(): ?string
    {
        return $this->due_date?->format('m/Y');
    }

    public function getAutomaticStatusAttribute(): string
    {
        // Se marcata manualmente come rinnovata, preserviamo quel valore.
        if ($this->is_renewed) {
            return self::STATUS_RENEWED;
        }

        if (!$this->due_date) {
            return self::STATUS_PENDING;
        }

        $today = Carbon::today();

        if ($this->due_date->isBefore($today)) {
            return self::STATUS_EXPIRED;
        }

        if ($this->due_date->isAfter($today)) {
            return self::STATUS_VALID;
        }

        $warningMonths = max(0, (int) config('deadlines.warning_months', 2));
        $warningStartDate = $this->due_date->copy()->subMonthsNoOverflow($warningMonths);

        return $today->gte($warningStartDate) ? self::STATUS_PENDING : self::STATUS_RENEWED;
    }

    public function syncStatusFromRules(): void
    {
        // Sincronizza lo stato persistito con le regole temporali correnti.
        if (!$this->due_date) {
            return;
        }

        $today = Carbon::today();
        $warningMonths = max(0, (int) config('deadlines.warning_months', 2));
        $warningStartDate = $this->due_date->copy()->subMonthsNoOverflow($warningMonths);

        if ($this->due_date->isBefore($today)) {
            $newStatus = self::STATUS_EXPIRED;
        } elseif ($this->due_date->isAfter($today)) {
            $newStatus = self::STATUS_VALID;
        } elseif ($today->gte($warningStartDate)) {
            $newStatus = self::STATUS_PENDING;
        } else {
            $newStatus = self::STATUS_RENEWED;
        }

        if ($this->status !== $newStatus) {
            $this->status = $newStatus;
            $this->is_renewed = ($newStatus === self::STATUS_RENEWED);
            $this->save();
        }
    }

    public static function calculateMinisterialDueDateForVehicle(Vehicle $vehicle, ?int $excludeDeadlineId = null): ?Carbon
    {
        if (!$vehicle->immatricolation_date || !$vehicle->vehicleType) {
            return null;
        }

        $query = self::query()
            ->where('vehicle_id', $vehicle->id)
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

        $query = self::query()
            ->where('vehicle_id', $vehicle->id)
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

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
