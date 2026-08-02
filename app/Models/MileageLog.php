<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class MileageLog extends Model
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
