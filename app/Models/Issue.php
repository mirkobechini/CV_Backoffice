<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Issue extends Model
{
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
