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
        'notes',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'return_date' => 'date',
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
     * Non è una colonna: un appuntamento può coinvolgere attrezzature di
     * veicoli (e quindi gruppi) diversi, quindi non esiste "il" gruppo di
     * un appuntamento in modo univoco. Torna sempre null, lo stesso
     * comportamento già usato per l'attrezzatura senza veicolo assegnato
     * (permissivo, visibile a tutti i gruppi): HasGroupScopedAccess lo
     * tratta come "nessun gruppo da verificare". L'isolamento resta comunque
     * garantito a livello di query nelle liste (forCurrentUser() sulle
     * attrezzature collegate).
     */
    public function getVehicleAttribute(): null
    {
        return null;
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
