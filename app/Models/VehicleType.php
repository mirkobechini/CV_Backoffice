<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleType extends Model
{
    protected $fillable = [
        'name',
        'needs_oxygen_check',
        'first_inspection_months',
        'regular_inspection_months',
        'first_tagliando_km',
        'regular_tagliando_km',
    ];

    protected $casts = [
        'needs_oxygen_check' => 'boolean',
        'first_inspection_months' => 'integer',
        'regular_inspection_months' => 'integer',
        'first_tagliando_km' => 'integer',
        'regular_tagliando_km' => 'integer',
    ];

    /**
     * Km per il primo tagliando (da veicolo nuovo). Default 25.000.
     */
    public function getFirstTagliandoKmAttribute(): ?int
    {
        return $this->attributes['first_tagliando_km'] ?? 25000;
    }

    /**
     * Km per i tagliandi successivi. Default 20.000.
     */
    public function getRegularTagliandoKmAttribute(): ?int
    {
        return $this->attributes['regular_tagliando_km'] ?? 20000;
    }

    public function vehicles()
    {
        // Un tipo ha molti (hasMany) mezzi
        return $this->hasMany(Vehicle::class);
    }

    public function equipmentTypes()
    {
        return $this->belongsToMany(EquipmentType::class, 'vehicle_type_equipment_requirements', 'vehicle_type_id', 'equipment_type_id')
            ->withPivot('required_quantity');
    }
}
