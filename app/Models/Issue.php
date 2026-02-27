<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Issue extends Model
{
    protected $casts = [
        'event_date' => 'date',
    ];

    public function getEventDateFormattedAttribute(): ?string
    {
        return $this->event_date?->format('d/m/Y');
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
