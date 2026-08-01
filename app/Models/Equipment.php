<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Equipment extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    protected $fillable = [
        'vehicle_id',
        'equipment_type_id',
        'name',
        'serial_number',
        'revision_date',
        'expiration_date',
    ];

    protected $casts = [
        'revision_date' => 'date',
        'expiration_date' => 'date',
    ];

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', Carbon::today()->addDays($days))
            ->orderBy('expiration_date');
    }

    public function getRevisionDateFormattedAttribute(): ?string
    {
        return $this->revision_date?->format('m/Y');
    }

    public function getExpirationDateFormattedAttribute(): ?string
    {
        return $this->expiration_date?->format('m/Y');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status_label) {
            'Scaduta' => 'red',
            'In scadenza' => 'yellow',
            'Valida' => 'green',
            default => 'blue',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        if (!$this->expiration_date) {
            return 'N/A';
        }

        $today = now()->startOfDay();
        $expiration = $this->expiration_date->startOfDay();

        if ($expiration->isPast()) {
            return 'Scaduta';
        }

        if ($expiration->diffInDays($today) <= 30) {
            return 'In scadenza';
        }

        return 'Valida';
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function equipmentType()
    {
        return $this->belongsTo(EquipmentType::class, 'equipment_type_id');
    }
}
