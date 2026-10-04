<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EquipmentMaintenanceRecord extends Model
{
    use SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    public const ACTIVITY_REVISIONE = 'Revisione';

    public const ACTIVITY_COLLAUDO = 'Collaudo';

    public const ACTIVITY_RIPARAZIONE = 'Riparazione';

    public const ACTIVITY_SOSTITUZIONE = 'Sostituzione';

    public const ACTIVITY_TYPES = [
        self::ACTIVITY_REVISIONE,
        self::ACTIVITY_COLLAUDO,
        self::ACTIVITY_RIPARAZIONE,
        self::ACTIVITY_SOSTITUZIONE,
        'Altro',
    ];

    protected $fillable = [
        'provider_id',
        'appointment_date',
        'return_date',
        'activity_type',
        'cost',
        'notes',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'return_date' => 'date',
        'cost' => 'decimal:2',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * Le attrezzature coinvolte: a differenza di un appuntamento veicolo
     * (sempre un solo veicolo), un appuntamento attrezzature può
     * riguardarne più di una insieme (es. collaudo di più estintori dallo
     * stesso fornitore nello stesso giorno).
     */
    public function equipments()
    {
        return $this->belongsToMany(Equipment::class, 'equipment_maintenance_record_equipment');
    }

    public function issues()
    {
        return $this->hasMany(EquipmentIssue::class, 'equipment_maintenance_record_id');
    }

    /**
     * Non è una colonna: un appuntamento può coinvolgere più attrezzature,
     * ma nel caso comune (una sola, o più tutte dello stesso veicolo) c'è
     * comunque "il" gruppo proprietario da verificare — tornare sempre
     * null lascerebbe autorizzazione update/delete senza alcun controllo
     * di gruppo anche in quel caso comune (le liste restano filtrate per
     * query, ma l'accesso diretto per id no). Torna il veicolo condiviso
     * solo se le attrezzature collegate (che ne hanno uno) appartengono
     * TUTTE allo stesso veicolo; altrimenti null — stesso comportamento
     * permissivo già usato per l'attrezzatura senza veicolo assegnato,
     * riservato ai casi davvero ambigui (nessuna attrezzatura assegnata, o
     * attrezzature di veicoli diversi).
     */
    public function getVehicleAttribute(): ?Vehicle
    {
        $vehicles = $this->equipments->map(fn (Equipment $e) => $e->vehicle)->filter()->unique('id');

        return $vehicles->count() === 1 ? $vehicles->first() : null;
    }

    public function getAppointmentDateFormattedAttribute(): ?string
    {
        return $this->appointment_date?->format('d/m/Y');
    }

    public function getReturnDateFormattedAttribute(): ?string
    {
        return $this->return_date?->format('d/m/Y');
    }
}
