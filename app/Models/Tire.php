<?php

namespace App\Models;

use App\Models\Concerns\HasVehicleGroupScoping;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\HasActivity;
use Spatie\Activitylog\Support\LogOptions;

class Tire extends Model
{
    use HasActivity, HasVehicleGroupScoping, SoftDeletes;

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
     * Assale di una posizione, usato per confrontare una gomma con le
     * misure consigliate del veicolo per assale (Vehicle::allowed_tire_sizes):
     * alcune misure del libretto valgono solo per l'anteriore o il
     * posteriore, non per entrambi.
     */
    public const AXLE_FRONT = 'front';

    public const AXLE_REAR = 'rear';

    public static function axleForPosition(string $position): string
    {
        return str_starts_with($position, 'front_') ? self::AXLE_FRONT : self::AXLE_REAR;
    }

    /**
     * Formato canonico di una misura pneumatico (es. "225/75R16C 121/120Q"),
     * lo stesso composto da <x-form.tire-size-input>. Condiviso da Tire,
     * MaintenanceRecord (new_tire_size) e Vehicle (allowed_tire_size) così
     * i tre punti restano sempre confrontabili direttamente come stringhe.
     */
    public const SIZE_REGEX = '/^\d{2,3}\/\d{2,3}R\d{2}C?(\s[A-Z0-9\/]{2,10})?$/i';

    /**
     * Normalizza sempre in maiuscolo (R, C, indice carico/velocità come
     * "99/97H"): senza questo, la stessa misura finiva salvata in forme
     * diverse a seconda di come l'utente la digitava (es. "91v" vs "91V"),
     * rendendo due gomme identiche difficili da confrontare a colpo d'occhio.
     */
    public function setSizeAttribute(?string $value): void
    {
        $this->attributes['size'] = $value === null ? null : strtoupper($value);
    }

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
        return $this->belongsToMany(Issue::class, 'issue_tire');
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
     * Avviso non bloccante (non una regola di validazione) in due casi:
     *
     * 1. La misura inserita non corrisponde a nessuna di quelle consigliate
     *    per il veicolo (Vehicle::allowed_tire_sizes, forma {size, code}).
     * 2. La misura corrisponde a un codice noto, ma quel codice non compare
     *    mai sull'assale indicato nella tabella delle combinazioni ammesse
     *    (Vehicle::allowed_tire_size_combinations, forma {front_code,
     *    rear_code}) — es. un codice che sul libretto può andare solo
     *    davanti, montato dietro. Senza $position, o senza combinazioni
     *    definite per il veicolo, questo secondo controllo non si applica
     *    (permissivo, come quando mancano dati).
     *
     * Non verifica che la coppia ESATTA montata sui due assi sia tra le
     * combinazioni ammesse (richiederebbe interrogare la gomma sull'assale
     * opposto ad ogni salvataggio) — solo che il codice di questa misura
     * possa mai comparire su questo assale. Tutte le misure passano dallo
     * stesso componente/formato canonico, quindi un confronto diretto
     * (case-insensitive) è affidabile.
     */
    public static function sizeMismatchWarning(?string $enteredSize, ?Vehicle $vehicle, ?string $position = null): ?string
    {
        $allowedEntries = $vehicle?->allowed_tire_sizes ?? [];

        if (! $enteredSize || empty($allowedEntries)) {
            return null;
        }

        $enteredSize = trim($enteredSize);

        $matchedEntry = collect($allowedEntries)->first(
            fn ($entry) => filled($entry['size'] ?? null) && strcasecmp($enteredSize, trim($entry['size'])) === 0
        );

        if (! $matchedEntry) {
            $allowedList = collect($allowedEntries)
                ->map(fn ($entry) => trim(($entry['code'] ?? '').': '.($entry['size'] ?? ''), ': '))
                ->implode(', ');

            return "La misura inserita ({$enteredSize}) non corrisponde a nessuna delle misure consigliate per questo veicolo ({$allowedList}).";
        }

        $code = $matchedEntry['code'] ?? null;
        $combinations = $vehicle->allowed_tire_size_combinations ?? [];

        if (! $position || ! $code || empty($combinations)) {
            return null;
        }

        $axle = self::axleForPosition($position);
        $axleKey = $axle === self::AXLE_FRONT ? 'front_code' : 'rear_code';

        $allowedOnAxle = collect($combinations)->contains(fn ($combo) => ($combo[$axleKey] ?? null) === $code);

        if ($allowedOnAxle) {
            return null;
        }

        $axleLabel = $axle === self::AXLE_FRONT ? 'anteriore' : 'posteriore';

        return "La misura {$enteredSize} (codice {$code}) non risulta tra le combinazioni ammesse per l'asse {$axleLabel}.";
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
