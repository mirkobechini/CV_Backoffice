<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MaintenanceRecordItem extends Model
{
    protected $fillable = [
        'maintenance_record_id',
        'itemable_id',
        'itemable_type',
    ];

    public function maintenanceRecord()
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }
}
