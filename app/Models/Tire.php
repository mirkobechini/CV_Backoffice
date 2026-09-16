<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Tire extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    public const SEASON_SUMMER = 'summer';

    public const SEASON_WINTER = 'winter';

    public const SEASON_ALL_SEASON = 'all_season';

    public const STATUS_MOUNTED = 'mounted';

    public const STATUS_STORED = 'stored';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'vehicle_id',
        'season',
        'quantity',
        'brand',
        'model_name',
        'size',
        'status',
        'mounted_date',
        'mounted_mileage',
        'next_change_date',
        'next_change_mileage',
        'notes',
    ];

    protected $casts = [
        'mounted_date' => 'date',
        'next_change_date' => 'date',
        'quantity' => 'integer',
        'mounted_mileage' => 'integer',
        'next_change_mileage' => 'integer',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function issues()
    {
        return $this->hasMany(Issue::class);
    }

    public function changes()
    {
        return $this->hasMany(TireChange::class);
    }

    public function getSeasonLabelAttribute(): string
    {
        return match ($this->season) {
            self::SEASON_SUMMER => 'Estive',
            self::SEASON_WINTER => 'Invernali',
            self::SEASON_ALL_SEASON => 'Quattro stagioni',
            default => 'N/A',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_MOUNTED => 'Montate',
            self::STATUS_STORED => 'In magazzino',
            self::STATUS_RETIRED => 'Dismesse',
            default => 'N/A',
        };
    }

    public function getMountedDateFormattedAttribute(): ?string
    {
        return $this->mounted_date?->format('d/m/Y');
    }

    public function getNextChangeDateFormattedAttribute(): ?string
    {
        return $this->next_change_date?->format('d/m/Y');
    }

    /**
     * True se il cambio è scaduto (per data o per km), calcolato rispetto
     * al chilometraggio attuale del veicolo, se disponibile.
     */
    public function getChangeDueAttribute(): bool
    {
        if ($this->status !== self::STATUS_MOUNTED) {
            return false;
        }

        if ($this->next_change_date && $this->next_change_date->isPast()) {
            return true;
        }

        if ($this->next_change_mileage !== null) {
            $currentMileage = $this->vehicle?->mileage;
            if ($currentMileage !== null && $currentMileage >= $this->next_change_mileage) {
                return true;
            }
        }

        return false;
    }
}
