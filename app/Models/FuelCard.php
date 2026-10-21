<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\HasActivity;
use Spatie\Activitylog\Support\LogOptions;

class FuelCard extends Model
{
    use HasActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_JOLLY = 'jolly';

    public const STATUS_UNASSIGNED = 'unassigned';

    /**
     * Tessera sostituita da una nuova: a differenza di jolly/unassigned,
     * mantiene il vehicle_id (non obbligatorio né vietato, a differenza di
     * "assigned" dove è richiesto) per restare traccia di quale veicolo la
     * usava, invece di perdere quel legame una volta tolta dalla
     * circolazione.
     */
    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'card_number',
        'provider',
        'status',
        'vehicle_id',
        'group_id',
        'notes',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function fuelLogs()
    {
        return $this->hasMany(FuelLog::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ASSIGNED => 'Assegnata',
            self::STATUS_JOLLY => 'Jolly (condivisa)',
            self::STATUS_RETIRED => 'Vecchia (sostituita)',
            default => 'Non assegnata',
        };
    }
}
