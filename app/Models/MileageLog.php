<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MileageLog extends Model
{
    protected $fillable = [
        'vehicle_id',
        'log_date',
        'mileage',
    ];

    protected $casts = [
        'log_date' => 'date',
    ];

    public function getLogDateFormattedAttribute(): ?string
    {
        return $this->log_date?->format('d/m/Y');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
