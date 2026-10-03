<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EquipmentIssue extends Model
{
    use SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    protected $fillable = [
        'equipment_id',
        'equipment_maintenance_record_id',
        'description',
        'status',
        'event_date',
    ];

    protected $casts = [
        'event_date' => 'date',
    ];

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'in_progress']);
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function maintenanceRecord()
    {
        return $this->belongsTo(EquipmentMaintenanceRecord::class, 'equipment_maintenance_record_id');
    }

    /**
     * Non è una colonna: risale al veicolo dell'attrezzatura collegata
     * (null se l'attrezzatura non è assegnata a nessun veicolo). Permette a
     * HasGroupScopedAccess di applicare lo stesso controllo di gruppo già
     * usato per gli altri modelli legati (indirettamente) a un veicolo,
     * senza bisogno di una propria implementazione.
     */
    public function getVehicleAttribute(): ?Vehicle
    {
        return $this->equipment?->vehicle;
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
}
