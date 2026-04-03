<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentType extends Model
{
    protected $fillable = [
        'name',
        'first_inspection_months',
        'regular_inspection_months',
    ];


    public function getFirstInspectionMonthsFormattedAttribute(): ?string
    {
        return $this->first_inspection_months?->format('m/Y');
    }

    public function getRegularInspectionMonthsFormattedAttribute(): ?string
    {
        return $this->regular_inspection_months?->format('m/Y');
    }

    public function equipments()
    {
        return $this->hasMany(Equipment::class, 'equipment_type_id');
    }

    public function vehicleTypes(){
        return $this->belongsToMany(VehicleType::class, 'vehicle_type_equipment_requirements', 'equipment_type_id', 'vehicle_type_id')
        ->withPivot('required_quantity');
    }
}
