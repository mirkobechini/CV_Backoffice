<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\HasActivity;
use Spatie\Activitylog\Support\LogOptions;

class FleetDeviceAssignment extends Model
{
    use HasActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    protected $fillable = [
        'fleet_device_id',
        'vehicle_id',
        'previous_vehicle_id',
        'plate_snapshot',
        'assigned_date',
        'notes',
    ];

    protected $casts = [
        'assigned_date' => 'date',
    ];

    public function fleetDevice()
    {
        return $this->belongsTo(FleetDevice::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function previousVehicle()
    {
        return $this->belongsTo(Vehicle::class, 'previous_vehicle_id');
    }

    public function getAssignedDateFormattedAttribute(): ?string
    {
        return $this->assigned_date?->format('d/m/Y');
    }
}
