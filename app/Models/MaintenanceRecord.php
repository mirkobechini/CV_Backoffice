<?php

namespace App\Models;

use App\Models\Concerns\HasVehicleGroupScoping;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\HasActivity;
use Spatie\Activitylog\Support\LogOptions;

class MaintenanceRecord extends Model
{
    use HasActivity, HasVehicleGroupScoping, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    public const ACTIVITY_TAGLIANDO = 'Tagliando';

    public const ACTIVITY_REPAIR = 'Riparazione';

    public const ACTIVITY_REVISION_MINISTERIAL = 'Revisione Ministeriale';

    public const ACTIVITY_REVISION_OXYGEN = 'Revisione Impianto Ossigeno';

    public const ACTIVITY_TIMING_BELT = 'Cinghia Distribuzione';

    public const ACTIVITY_TIRE_CHANGE = 'Cambio Gomme';

    public const ACTIVITY_WASH = 'Lavaggio';

    public const ACTIVITY_TYPES = [
        self::ACTIVITY_TAGLIANDO,
        self::ACTIVITY_REPAIR,
        self::ACTIVITY_REVISION_MINISTERIAL,
        self::ACTIVITY_REVISION_OXYGEN,
        self::ACTIVITY_TIMING_BELT,
        self::ACTIVITY_WASH,
        self::ACTIVITY_TIRE_CHANGE,
        'Altro',
    ];

    protected $fillable = [
        'vehicle_id',
        'provider_id',
        'appointment_date',
        'appointment_time',
        'return_date',
        'activity_type',
        'mileage_at_service',
        'cost',
        'notes',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'return_date' => 'date',
        'mileage_at_service' => 'integer',
        'cost' => 'decimal:2',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function items()
    {
        return $this->hasMany(MaintenanceRecordItem::class);
    }

    /**
     * Veicoli "attualmente in officina" (dashboard, stato flotta pubblico):
     * non ancora rientrati, e il cui appuntamento è già iniziato — oggi
     * senza un orario specifico vale da mezzanotte (come prima), ma con un
     * orario impostato (es. 15:00) il veicolo risulta in officina solo da
     * quell'ora, non dall'inizio della giornata (bug segnalato 2026-10-19).
     */
    public function scopeCurrentlyInWorkshop(Builder $query): Builder
    {
        $today = Carbon::today()->toDateString();
        $now = Carbon::now()->format('H:i');

        return $query->whereNull('return_date')
            ->where(function (Builder $q) use ($today, $now) {
                $q->whereDate('appointment_date', '<', $today)
                    ->orWhere(function (Builder $q2) use ($today, $now) {
                        $q2->whereDate('appointment_date', $today)
                            ->where(function (Builder $q3) use ($now) {
                                $q3->whereNull('appointment_time')
                                    ->orWhere('appointment_time', '<=', $now);
                            });
                    });
            });
    }

    /**
     * Complemento esatto di scopeCurrentlyInWorkshop() sopra: un appuntamento
     * di oggi con un orario non ancora raggiunto resta "prossimo" invece che
     * "in officina" — le due card dashboard non devono mai sovrapporsi né
     * lasciare un appuntamento fuori da entrambe.
     */
    public function scopeNotYetStarted(Builder $query): Builder
    {
        $today = Carbon::today()->toDateString();
        $now = Carbon::now()->format('H:i');

        return $query->where(function (Builder $q) use ($today, $now) {
            $q->whereDate('appointment_date', '>', $today)
                ->orWhere(function (Builder $q2) use ($today, $now) {
                    $q2->whereDate('appointment_date', $today)
                        ->whereNotNull('appointment_time')
                        ->where('appointment_time', '>', $now);
                });
        });
    }

    public function tires()
    {
        return $this->morphToMany(Tire::class, 'itemable', 'maintenance_record_items');
    }

    public function getAppointmentDateFormattedAttribute(): ?string
    {
        return $this->appointment_date?->format('d/m/Y');
    }

    /**
     * Data + orario insieme quando l'orario è impostato (facoltativo), solo
     * la data altrimenti — per non mostrare un posto vuoto o un orario
     * finto (es. 00:00) quando l'utente non l'ha specificato.
     */
    public function getAppointmentDateTimeFormattedAttribute(): ?string
    {
        if (! $this->appointment_date) {
            return null;
        }

        return $this->appointment_time
            ? $this->appointment_date->format('d/m/Y').' '.$this->appointment_time
            : $this->appointment_date->format('d/m/Y');
    }

    public function getReturnDateFormattedAttribute(): ?string
    {
        return $this->return_date?->format('d/m/Y');
    }

    /**
     * Descrizioni di tutti i guasti collegati, separate da virgola.
     */
    public function getIssueDescriptionsAttribute(): string
    {
        return $this->items
            ->where('itemable_type', Issue::class)
            ->map(fn ($item) => $item->itemable?->description)
            ->filter()
            ->implode(', ');
    }

    /**
     * Tipologie delle scadenze collegate (Tagliando, Revisione Ministeriale, ecc.), una sola volta ciascuna.
     */
    public function getDeadlineTypesAttribute(): string
    {
        return $this->items
            ->where('itemable_type', Deadline::class)
            ->map(fn ($item) => $item->itemable?->type)
            ->filter()
            ->unique()
            ->implode(', ');
    }

    /**
     * Descrizione combinata di guasti e scadenze collegate insieme.
     */
    public function getItemDescriptionsAttribute(): string
    {
        return collect([$this->issue_descriptions, $this->deadline_types])
            ->filter(fn ($part) => $part !== '')
            ->implode(' · ');
    }
}
