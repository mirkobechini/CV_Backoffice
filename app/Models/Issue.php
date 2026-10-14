<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\HasActivity;
use Spatie\Activitylog\Support\LogOptions;

class Issue extends Model
{
    use HasActivity, Searchable, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    /**
     * Vocabolario fisso della parte del veicolo coinvolta, per abilitare
     * statistiche sui guasti per parte (prima solo descrizione libera, non
     * aggregabile). Un guasto = una parte sola: se un evento ne coinvolge
     * più di una (es. un incidente), si aprono più guasti collegati allo
     * stesso appuntamento, come già avviene oggi.
     */
    public const PART_MOTORE = 'Motore';

    public const PART_FRENI = 'Freni';

    public const PART_SOSPENSIONI = 'Sospensioni';

    public const PART_PNEUMATICI = 'Pneumatici';

    public const PART_ELETTRONICA = 'Elettronica/Elettrico';

    public const PART_CARROZZERIA = 'Carrozzeria';

    public const PART_CLIMATIZZAZIONE = 'Climatizzazione';

    public const PART_TRASMISSIONE = 'Trasmissione/Cambio';

    public const PART_IMPIANTO_SANITARIO = 'Impianto sanitario';

    public const PART_ILLUMINAZIONE_SIRENA = 'Illuminazione/Sirena';

    public const PART_ALTRO = 'Altro';

    public const PARTS = [
        self::PART_MOTORE,
        self::PART_FRENI,
        self::PART_SOSPENSIONI,
        self::PART_PNEUMATICI,
        self::PART_ELETTRONICA,
        self::PART_CARROZZERIA,
        self::PART_CLIMATIZZAZIONE,
        self::PART_TRASMISSIONE,
        self::PART_IMPIANTO_SANITARIO,
        self::PART_ILLUMINAZIONE_SIRENA,
        self::PART_ALTRO,
    ];

    protected $fillable = [
        'vehicle_id',
        'part',
        'description',
        'notes',
        'status',
        'photo',
        'event_date',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    protected $searchable = ['description', 'status'];

    protected $fulltextable = ['description'];

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'in_progress']);
    }

    public function getEventDateFormattedAttribute(): ?string
    {
        return $this->event_date?->format('d/m/Y');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'open' => 'red',
            'in_progress' => 'yellow',
            'closed' => 'green',
            default => 'blue',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'open' => 'Aperto',
            'in_progress' => 'In lavorazione',
            'closed' => 'Risolto',
            default => $this->status,
        };
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function tires()
    {
        return $this->belongsToMany(Tire::class, 'issue_tire');
    }

    public function maintenanceRecordItems()
    {
        return $this->morphMany(MaintenanceRecordItem::class, 'itemable');
    }
}
