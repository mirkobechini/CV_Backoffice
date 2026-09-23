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

    public const POSITION_FRONT_LEFT = 'front_left';

    public const POSITION_FRONT_RIGHT = 'front_right';

    public const POSITION_REAR_LEFT = 'rear_left';

    public const POSITION_REAR_RIGHT = 'rear_right';

    public const POSITIONS = [
        self::POSITION_FRONT_LEFT,
        self::POSITION_FRONT_RIGHT,
        self::POSITION_REAR_LEFT,
        self::POSITION_REAR_RIGHT,
    ];

    protected $fillable = [
        'vehicle_id',
        'season',
        'position',
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

    /**
     * Posizioni coperte da una descrizione di gomma(e) nuova(e) nel form
     * "Cambio Gomme": una singola posizione esplicita, una coppia
     * anteriore/posteriore, o il set completo. Condivisa tra
     * ValidatesTireSelection (validazione) e MaintenanceRecordController
     * (creazione effettiva delle righe).
     */
    public static function positionsForGroup(?string $group, ?string $singlePosition): array
    {
        return match ($group) {
            'front_pair' => [self::POSITION_FRONT_LEFT, self::POSITION_FRONT_RIGHT],
            'rear_pair' => [self::POSITION_REAR_LEFT, self::POSITION_REAR_RIGHT],
            'full_set' => self::POSITIONS,
            default => array_filter([$singlePosition]),
        };
    }

    public function getPositionLabelAttribute(): string
    {
        return match ($this->position) {
            self::POSITION_FRONT_LEFT => 'Anteriore sinistra',
            self::POSITION_FRONT_RIGHT => 'Anteriore destra',
            self::POSITION_REAR_LEFT => 'Posteriore sinistra',
            self::POSITION_REAR_RIGHT => 'Posteriore destra',
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
