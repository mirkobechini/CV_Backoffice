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

    /**
     * Formato canonico di una misura pneumatico (es. "225/75R16C 121/120Q"),
     * lo stesso composto da <x-form.tire-size-input>. Condiviso da Tire,
     * MaintenanceRecord (new_tire_size) e Vehicle (allowed_tire_size) così
     * i tre punti restano sempre confrontabili direttamente come stringhe.
     */
    public const SIZE_REGEX = '/^\d{2,3}\/\d{2,3}R\d{2}C?(\s[A-Z0-9\/]{2,10})?$/i';

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
    /**
     * Avviso non bloccante (non una regola di validazione) quando la misura
     * inserita non corrisponde a nessuna di quelle consigliate per il
     * veicolo (un veicolo può averne più di una, es. assale anteriore
     * diverso dal posteriore): tutte passano dallo stesso componente/
     * formato canonico, quindi un confronto diretto (case-insensitive) è
     * affidabile. Nessun avviso se il veicolo non ha misure consigliate
     * impostate.
     */
    public static function sizeMismatchWarning(?string $enteredSize, ?Vehicle $vehicle): ?string
    {
        $allowedSizes = $vehicle?->allowed_tire_sizes ?? [];

        if (! $enteredSize || empty($allowedSizes)) {
            return null;
        }

        $enteredSize = trim($enteredSize);
        $matches = collect($allowedSizes)->contains(fn ($size) => strcasecmp($enteredSize, trim($size)) === 0);

        if ($matches) {
            return null;
        }

        $allowedList = implode(', ', $allowedSizes);

        return "La misura inserita ({$enteredSize}) non corrisponde a nessuna delle misure consigliate per questo veicolo ({$allowedList}).";
    }

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
