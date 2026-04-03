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

    public function equipmentTypes(){
        return $this->belongsToMany(EquipmentType::class, 'vehicle_type_equipment_requirements', 'vehicle_type_id', 'equipment_type_id')
        ->withPivot('required_quantity');
    }
}
