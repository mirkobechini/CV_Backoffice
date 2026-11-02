<?php

namespace App\Models;

use App\Models\Concerns\HasVehicleGroupScoping;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\HasActivity;
use Spatie\Activitylog\Support\LogOptions;

class FuelLog extends Model
{
    use HasActivity, HasVehicleGroupScoping;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    protected $fillable = [
        'vehicle_id',
        'fuel_card_id',
        'fuel_type',
        'log_date',
        'liters',
        'cost_total',
        'mileage',
    ];

    protected $casts = [
        'log_date' => 'date',
        'liters' => 'decimal:2',
        'cost_total' => 'decimal:2',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function fuelCard()
    {
        return $this->belongsTo(FuelCard::class);
    }

    public function getLogDateFormattedAttribute(): ?string
    {
        return $this->log_date?->format('d/m/Y');
    }

    /**
     * Tipo carburante del singolo rifornimento, non del veicolo: un
     * veicolo "ibrido" benzina/metano non fa un pieno di "ibrido", quindi
     * qui non c'è quel valore (vedi Vehicle::fuelTypeLabels()).
     *
     * @return array<string, string>
     */
    public static function fuelTypeLabels(): array
    {
        return [
            'benzina' => __('Benzina'),
            'diesel' => __('Diesel'),
            'elettrico' => __('Elettrico'),
            'metano' => __('Metano'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function fuelTypeBadgeColors(): array
    {
        return [
            'benzina' => 'yellow',
            'diesel' => 'blue',
            'elettrico' => 'green',
            'metano' => 'purple',
        ];
    }

    public function getFuelTypeLabelAttribute(): ?string
    {
        return $this->fuel_type ? (self::fuelTypeLabels()[$this->fuel_type] ?? $this->fuel_type) : null;
    }

    public function getCostPerLiterAttribute(): ?float
    {
        if (! $this->cost_total || ! $this->liters) {
            return null;
        }

        return round((float) $this->cost_total / (float) $this->liters, 3);
    }

    /**
     * Consumo (km percorsi con questo pieno / litri di questo pieno), solo
     * se sia questo rifornimento che il precedente dello stesso veicolo
     * hanno un chilometraggio registrato: senza entrambi i km non c'è modo
     * di sapere quanta strada ha coperto questo pieno.
     */
    public static function consumptionKmPerLiter(self $log, ?self $previous): ?float
    {
        if (! $previous || $log->mileage === null || $previous->mileage === null) {
            return null;
        }

        $km = $log->mileage - $previous->mileage;

        if ($km <= 0 || (float) $log->liters <= 0) {
            return null;
        }

        return round($km / (float) $log->liters, 2);
    }
}
