<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    public function vehicles()
    {
        // Un tipo ha molti (hasMany) mezzi
        return $this->hasMany(Vehicle::class);
    }
}
