<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class MileageLog extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    protected $fillable = [
        'vehicle_id',
        'log_date',
        'mileage',
    ];

    protected $casts = [
        'log_date' => 'date',
    ];

    public function getLogDateFormattedAttribute(): ?string
    {
        return $this->log_date?->format('d/m/Y');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Verifica che un chilometraggio sia coerente con la cronologia del veicolo:
     * non può essere inferiore all'ultima lettura precedente alla data data, né
     * superiore alla prima lettura successiva. Questo permette di inserire letture
     * "storiche" (con data antecedente a letture già registrate) purché restino
     * coerenti con l'ordine cronologico dei km.
     *
     * Restituisce il messaggio di errore da mostrare, o null se tutto ok.
     */
    public static function findChronologyConflict(int $vehicleId, $logDate, int $mileage, ?int $excludeId = null): ?string
    {
        $previous = static::where('vehicle_id', $vehicleId)
            ->where('log_date', '<', $logDate)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->orderByDesc('log_date')
            ->first();

        if ($previous && $mileage < $previous->mileage) {
            return 'Il chilometraggio non può essere inferiore a quello registrato il '
                .$previous->log_date->format('d/m/Y').' ('.number_format($previous->mileage, 0, ',', '.').' km).';
        }

        $next = static::where('vehicle_id', $vehicleId)
            ->where('log_date', '>', $logDate)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->orderBy('log_date')
            ->first();

        if ($next && $mileage > $next->mileage) {
            return 'Il chilometraggio non può essere superiore a quello registrato il '
                .$next->log_date->format('d/m/Y').' ('.number_format($next->mileage, 0, ',', '.').' km).';
        }

        return null;
    }
}
