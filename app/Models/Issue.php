<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class Issue extends Model
{
    use SoftDeletes, LogsActivity;

    protected static $logAttributes = ['*'];
    protected static $logOnlyDirty = true;
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

    public function maintenanceRecords()
    {
        return $this->hasMany(MaintenanceRecord::class);
    }
}
