<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $casts = [
        'immatricolation_date' => 'date',
        'warranty_expiration_date' => 'date',
    ];

    public function getImmatricolationDateFormattedAttribute(): ?string
    {
        return $this->immatricolation_date?->format('d/m/Y');
    }

    public function getWarrantyExpirationDateFormattedAttribute(): ?string
    {
        return $this->warranty_expiration_date?->format('d/m/Y');
    }

    public function getIsWarrantyExpiredAttribute(): bool
    {
        if (!$this->warranty_expiration_date) {
            return true;
        }

        return $this->warranty_expiration_date->isPast();
    }


    public function vehicleType()
    {
        // Un mezzo appartiene a (belongsTo) un tipo
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }


    public function issues()
    {
        return $this->hasMany(Issue::class);
    }

    public function maintenanceRecords()
    {
        return $this->hasMany(MaintenanceRecord::class);
    }
}
