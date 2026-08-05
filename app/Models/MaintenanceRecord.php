<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class MaintenanceRecord extends Model
{
    use SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    public const ACTIVITY_TAGLIANDO = 'Tagliando';
    public const ACTIVITY_REVISION_MINISTERIAL = 'Revisione Ministeriale';
    public const ACTIVITY_REVISION_OXYGEN = 'Revisione Impianto Ossigeno';

    public const ACTIVITY_TYPES = [
        self::ACTIVITY_TAGLIANDO,
        'Riparazione',
        self::ACTIVITY_REVISION_MINISTERIAL,
        self::ACTIVITY_REVISION_OXYGEN,
        'Lavaggio',
        'Cambio Gomme',
        'Altro',
    ];

    protected $fillable = [
        'vehicle_id',
        'provider_id',
        'appointment_date',
        'return_date',
        'activity_type',
        'mileage_at_service',
        'recurrence_months',
        'recurrence_km',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'return_date' => 'date',
        'mileage_at_service' => 'integer',
        'recurrence_months' => 'integer',
        'recurrence_km' => 'integer',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function items()
    {
        return $this->hasMany(MaintenanceRecordItem::class);
    }

    public function issues()
    {
        return $this->morphToMany(Issue::class, 'itemable', 'maintenance_record_items');
    }

    public function deadlines()
    {
        return $this->morphToMany(Deadline::class, 'itemable', 'maintenance_record_items');
    }

    public function getAppointmentDateFormattedAttribute(): ?string
    {
        return $this->appointment_date?->format('d/m/Y');
    }

    public function getReturnDateFormattedAttribute(): ?string
    {
        return $this->return_date?->format('d/m/Y');
    }

    /**
     * Data di scadenza del prossimo tagliando (se ricorrente).
     */
    public function getNextDueDateAttribute(): ?\Carbon\Carbon
    {
        $baseDate = $this->return_date ?? $this->appointment_date;
        if (!$baseDate || !$this->recurrence_months) {
            return null;
        }
        return $baseDate->copy()->addMonths($this->recurrence_months);
    }

    /**
     * Chilometraggio di scadenza del prossimo tagliando (se ricorrente).
     */
    public function getNextDueKmAttribute(): ?int
    {
        if (!$this->mileage_at_service || !$this->recurrence_km) {
            return null;
        }
        return $this->mileage_at_service + $this->recurrence_km;
    }

    /**
     * Quanti giorni mancano alla scadenza (negativo se scaduto).
     */
    public function getNextDueInDaysAttribute(): ?int
    {
        $dueDate = $this->next_due_date;
        if (!$dueDate) {
            return null;
        }
        return now()->startOfDay()->diffInDays($dueDate, false);
    }

    /**
     * Etichetta di stato scadenza.
     */
    public function getRecurrenceStatusAttribute(): string
    {
        $days = $this->next_due_in_days;
        if ($days === null) {
            return 'none';
        }
        if ($days < 0) {
            return 'expired';
        }
        if ($days <= 30) {
            return 'expiring';
        }
        return 'ok';
    }

    public function getRecurrenceStatusLabelAttribute(): string
    {
        $days = $this->next_due_in_days;
        if ($days === null) {
            return '—';
        }
        if ($days < 0) {
            return 'Scaduto da ' . abs($days) . ' gg';
        }
        if ($days === 0) {
            return 'Scade oggi';
        }
        if ($days === 1) {
            return 'Scade domani';
        }
        return 'Tra ' . $days . ' gg';
    }
}
