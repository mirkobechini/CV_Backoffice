<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    protected $fillable = [
        'vehicle_id',
        'equipment_type_id',
        'name',
        'serial_number',
        'revision_date',
        'expiration_date',
    ];

    protected $casts = [
        'revision_date' => 'date',
        'expiration_date' => 'date',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function equipmentType()
    {
        return $this->belongsTo(EquipmentType::class, 'equipment_type_id');
    }
}
