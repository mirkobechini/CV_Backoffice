<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Equipment extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): \Spatie\Activitylog\LogOptions
    {
        return \Spatie\Activitylog\LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    public const EXTINGUISHER_AGENT_CO2 = 'co2';

    public const EXTINGUISHER_AGENT_POWDER = 'powder';

    public const CHAIR_TYPE_ELECTRIC = 'electric';

    public const CHAIR_TYPE_MANUAL_2_WHEEL = 'manual_2_wheel';

    public const CHAIR_TYPE_MANUAL_4_WHEEL = 'manual_4_wheel';

    public const CHAIR_TYPE_MANUAL_TRACKS = 'manual_tracks';

    protected $fillable = [
        'vehicle_id',
        'equipment_type_id',
        'name',
        'brand',
        'model',
        'serial_number',
        'identification_number',
        'fabrication_date',
        'revision_date',
        'expiration_date',
        'extinguisher_agent',
        'weight_kg',
        'collaudo_date',
        'next_collaudo_date',
        'chair_type',
        'max_weight_kg',
        'notes',
    ];

    protected $casts = [
        'fabrication_date' => 'date',
        'revision_date' => 'date',
        'expiration_date' => 'date',
        'collaudo_date' => 'date',
        'next_collaudo_date' => 'date',
        'weight_kg' => 'decimal:2',
        'max_weight_kg' => 'decimal:2',
    ];

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', Carbon::today()->addDays($days))
            ->orderBy('expiration_date');
    }

    public function scopeCollaudoExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('next_collaudo_date')
            ->where('next_collaudo_date', '<=', Carbon::today()->addDays($days))
            ->orderBy('next_collaudo_date');
    }

    public function getFabricationDateFormattedAttribute(): ?string
    {
        return $this->fabrication_date?->format('m/Y');
    }

    public function getRevisionDateFormattedAttribute(): ?string
    {
        return $this->revision_date?->format('m/Y');
    }

    public function getExpirationDateFormattedAttribute(): ?string
    {
        return $this->expiration_date?->format('m/Y');
    }

    public function getCollaudoDateFormattedAttribute(): ?string
    {
        return $this->collaudo_date?->format('m/Y');
    }

    public function getNextCollaudoDateFormattedAttribute(): ?string
    {
        return $this->next_collaudo_date?->format('m/Y');
    }

    public function getExtinguisherAgentLabelAttribute(): ?string
    {
        return match ($this->extinguisher_agent) {
            self::EXTINGUISHER_AGENT_CO2 => 'CO2',
            self::EXTINGUISHER_AGENT_POWDER => 'Polvere',
            default => null,
        };
    }

    public function getChairTypeLabelAttribute(): ?string
    {
        return match ($this->chair_type) {
            self::CHAIR_TYPE_ELECTRIC => 'Elettrica',
            self::CHAIR_TYPE_MANUAL_2_WHEEL => 'Manuale a 2 ruote',
            self::CHAIR_TYPE_MANUAL_4_WHEEL => 'Manuale a 4 ruote',
            self::CHAIR_TYPE_MANUAL_TRACKS => 'Manuale cingolata',
            default => null,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status_label) {
            'Scaduta' => 'red',
            'In scadenza' => 'yellow',
            'Valida' => 'green',
            default => 'blue',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        if (! $this->expiration_date) {
            return 'N/A';
        }

        $today = now()->startOfDay();
        $expiration = $this->expiration_date->startOfDay();

        if ($expiration->isPast()) {
            return 'Scaduta';
        }

        // $today->diffInDays($expiration), non il contrario: da Carbon 3 il
        // segno dipende dall'ordine (l'oggetto su cui si chiama è il primo
        // termine della sottrazione), e con l'ordine invertito il risultato
        // era negativo per qualunque scadenza futura, quindi sempre <= 30 —
        // il badge "In scadenza" compariva anche a anni di distanza.
        if ($today->diffInDays($expiration) <= 30) {
            return 'In scadenza';
        }

        return 'Valida';
    }

    public function getCollaudoStatusColorAttribute(): string
    {
        return match ($this->collaudo_status_label) {
            'Scaduto' => 'red',
            'In scadenza' => 'yellow',
            'Valido' => 'green',
            default => 'blue',
        };
    }

    public function getCollaudoStatusLabelAttribute(): string
    {
        if (! $this->next_collaudo_date) {
            return 'N/A';
        }

        $today = now()->startOfDay();
        $due = $this->next_collaudo_date->startOfDay();

        if ($due->isPast()) {
            return 'Scaduto';
        }

        if ($today->diffInDays($due) <= 30) {
            return 'In scadenza';
        }

        return 'Valido';
    }

    /**
     * Numero di revisioni ordinarie registrate (esclude i collaudi):
     * usato per capire se un estintore ha raggiunto il numero massimo di
     * revisioni previsto dal suo tipo, oltre il quale va sostituito.
     */
    public function getRevisionCountAttribute(): int
    {
        return $this->revisions()->where('kind', EquipmentRevision::KIND_REVISION)->count();
    }

    /**
     * True se il numero di revisioni ha raggiunto (o superato) la soglia
     * di sostituzione configurata sul tipo di attrezzatura (rilevante
     * solo per gli estintori, ma calcolabile per qualunque tipo che
     * abbia impostato max_revisions_before_exchange).
     */
    public function getNeedsExchangeAttribute(): bool
    {
        $threshold = $this->equipmentType?->max_revisions_before_exchange;

        if (! $threshold) {
            return false;
        }

        return $this->revision_count >= $threshold;
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function equipmentType()
    {
        return $this->belongsTo(EquipmentType::class, 'equipment_type_id');
    }

    public function revisions()
    {
        return $this->hasMany(EquipmentRevision::class);
    }
}
