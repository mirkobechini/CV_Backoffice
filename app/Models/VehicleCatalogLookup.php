<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cache locale delle lookup al catalogo esterno openvehicle-data (vedi
 * OpenVehicleCatalogService), per non richiamare l'API per lo stesso
 * modello/variante di veicolo comprato più volte dalla stessa
 * associazione. "found" cachato anche quando false: evita di ricontattare
 * l'API per una variante che sappiamo non essere a catalogo.
 */
class VehicleCatalogLookup extends Model
{
    protected $fillable = [
        'type_code',
        'variant_code',
        'version_code',
        'found',
        'fuel_type',
        'engine_displacement_cc',
        'engine_power_kw',
        'max_mass_kg',
        'looked_up_at',
    ];

    protected $casts = [
        'found' => 'boolean',
        'engine_displacement_cc' => 'integer',
        'engine_power_kw' => 'integer',
        'max_mass_kg' => 'integer',
        'looked_up_at' => 'datetime',
    ];
}
