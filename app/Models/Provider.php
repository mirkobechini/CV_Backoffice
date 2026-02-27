<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    public function maintenanceRecords()
    {
        return $this->hasMany(MaintenanceRecord::class);
    }
}
