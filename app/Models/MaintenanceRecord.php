<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceRecord extends Model
{
    protected $table = 'maintenancerecords';

    protected $casts = [
        'appointment_date' => 'date',
        'return_date' => 'date',
    ];

    protected $fillable = [
        'vehicle_id',
        'provider_id',
        'issue_id',
        'appointment_date',
        'return_date',
        'activity_type',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function issue()
    {
        return $this->belongsTo(Issue::class);
    }

    public function getAppointmentDateFormattedAttribute(): ?string
    {
        return $this->appointment_date?->format('d/m/Y');
    }

    public function getReturnDateFormattedAttribute(): ?string
    {
        return $this->return_date?->format('d/m/Y');
    }
}
