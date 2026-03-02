<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    protected $fillable = [
        'name',
        'needs_oxygen_check',
        'extinguishers_required',
        'first_inspection_months',
        'regular_inspection_months',
    ];

    public function vehicles()
    {
        // Un tipo ha molti (hasMany) mezzi
        return $this->hasMany(Vehicle::class);
    }
}
