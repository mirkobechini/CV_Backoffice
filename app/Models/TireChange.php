<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TireChange extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    protected $fillable = [
        'vehicle_id',
        'tire_id',
        'previous_tire_id',
        'changed_date',
        'mileage_at_change',
        'notes',
    ];

    protected $casts = [
        'changed_date' => 'date',
        'mileage_at_change' => 'integer',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function tire()
    {
        return $this->belongsTo(Tire::class);
    }

    public function previousTire()
    {
        return $this->belongsTo(Tire::class, 'previous_tire_id');
    }

    public function getChangedDateFormattedAttribute(): ?string
    {
        return $this->changed_date?->format('d/m/Y');
    }
}
