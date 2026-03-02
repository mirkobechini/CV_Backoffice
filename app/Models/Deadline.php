<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Deadline extends Model
{
    public const TYPE_MINISTERIAL = 'Revisione Ministeriale';
    public const TYPE_OXYGEN = 'Revisione Impianto Ossigeno';
    public const OXYGEN_CHECK_INTERVAL_MONTHS = 12;

    protected $fillable = [
        'vehicle_id',
        'type',
        'due_date',
        'status',
    ];

    protected $casts = [
        'due_date' => 'date',
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
        if ($this->status === 'renewed') {
            return 'renewed';
        }

        if (!$this->due_date) {
            return 'pending';
        }

        $today = Carbon::today();

        if ($this->due_date->isBefore($today)) {
            return 'expired';
        }

        $warningMonths = max(0, (int) config('deadlines.warning_months', 2));
        $warningStartDate = $this->due_date->copy()->subMonthsNoOverflow($warningMonths);

        return $today->gte($warningStartDate) ? 'pending' : 'renewed';
    }

    public function syncStatusFromRules(): void
    {
        if (!$this->due_date) {
            return;
        }

        $today = Carbon::today();
        $warningMonths = max(0, (int) config('deadlines.warning_months', 2));
        $warningStartDate = $this->due_date->copy()->subMonthsNoOverflow($warningMonths);

        if ($this->due_date->isBefore($today)) {
            $newStatus = 'expired';
        } elseif ($today->gte($warningStartDate)) {
            $newStatus = 'pending';
        } else {
            $newStatus = 'renewed';
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

        $query = self::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('type', self::TYPE_MINISTERIAL)
            ->where('status', 'renewed')
            ->orderByDesc('due_date');

        if ($excludeDeadlineId !== null) {
            $query->where('id', '!=', $excludeDeadlineId);
        }

        $lastRenewedDeadline = $query->first();

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
            ->where('status', 'renewed')
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
