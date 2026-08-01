<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\Searchable;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;

class Issue extends Model
{
    use SoftDeletes, LogsActivity, Searchable;

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }
    protected $fillable = [
        'vehicle_id',
        'description',
        'status',
        'photo',
        'event_date',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    protected $searchable = ['description', 'status'];

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'in_progress']);
    }

    public function getEventDateFormattedAttribute(): ?string
    {
        return $this->event_date?->format('d/m/Y');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'open' => 'red',
            'in_progress' => 'yellow',
            'closed' => 'green',
            default => 'blue',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'open' => 'Aperto',
            'in_progress' => 'In lavorazione',
            'closed' => 'Risolto',
            default => $this->status,
        };
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function maintenanceRecordItems()
    {
        return $this->morphMany(MaintenanceRecordItem::class, 'itemable');
    }
}
