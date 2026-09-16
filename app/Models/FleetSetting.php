<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Impostazioni globali di flotta (non per-utente, non per-gruppo): al
 * momento solo le due date di cambio gomme stagionale. Riga singleton.
 */
class FleetSetting extends Model
{
    protected $fillable = [
        'winter_switch_date',
        'summer_switch_date',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'winter_switch_date' => '11-15',
            'summer_switch_date' => '04-15',
        ]);
    }
}
