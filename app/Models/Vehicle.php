<?php

namespace App\Models;

use App\Models\Concerns\HasEquipmentRequirements;
use App\Models\Concerns\HasFuelAndTireAttributes;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Concerns\HasActivity;
use Spatie\Activitylog\Support\LogOptions;

class Vehicle extends Model
{
    use HasActivity, HasEquipmentRequirements, HasFuelAndTireAttributes, Searchable, SoftDeletes;

    public const TIMING_BELT_TYPE_CHAIN = 'chain';

    public const TIMING_BELT_TYPE_DRY_BELT = 'dry_belt';

    public const TIMING_BELT_TYPE_OIL_BATH_BELT = 'oil_bath_belt';

    public const TIMING_BELT_TYPES = [
        self::TIMING_BELT_TYPE_CHAIN,
        self::TIMING_BELT_TYPE_DRY_BELT,
        self::TIMING_BELT_TYPE_OIL_BATH_BELT,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    protected $fillable = [
        'group_id',
        'license_plate',
        'internal_code',
        'brand_id',
        'car_model_id',
        'fuel_type',
        'allowed_fuel_types',
        'vehicle_type_id',
        'immatricolation_date',
        'registration_card_path',
        'warranty_expiration_date',
        'has_warranty_extension',
        'warranty_extension_duration',
        'timing_belt_type',
        'vin',
        'color',
        'seats',
        'environmental_class',
        'max_mass_kg',
        'engine_displacement_cc',
        'engine_power_kw',
        'vehicle_category',
        'allowed_tire_sizes',
        'allowed_tire_size_combinations',
    ];

    protected $casts = [
        'immatricolation_date' => 'date',
        'warranty_expiration_date' => 'date',
        'seats' => 'integer',
        'max_mass_kg' => 'integer',
        'engine_displacement_cc' => 'integer',
        'engine_power_kw' => 'integer',
        'allowed_tire_sizes' => 'array',
        'allowed_tire_size_combinations' => 'array',
        'allowed_fuel_types' => 'array',
    ];

    protected $searchable = ['internal_code', 'license_plate'];

    public function getOpenIssuesAttribute()
    {
        return $this->issues->whereIn('status', ['open', 'in_progress']);
    }

    public function getImmatricolationDateFormattedAttribute(): ?string
    {
        return $this->immatricolation_date?->format('d/m/Y');
    }

    public function getWarrantyExpirationDateFormattedAttribute(): ?string
    {
        return $this->warranty_expiration_date?->format('d/m/Y');
    }

    public function getIsWarrantyExpiredAttribute(): bool
    {
        if (! $this->warranty_expiration_date) {
            return true;
        }

        return $this->warranty_expiration_date->isPast();
    }

    public function getWarrantyOriginalExpirationDateAttribute(): ?string
    {
        $date = $this->warranty_expiration_date;

        if (! $date) {
            return null;
        }

        if ($this->has_warranty_extension && $this->warranty_extension_duration) {
            return $date->copy()->subMonths((int) $this->warranty_extension_duration)->toDateString();
        }

        return $date->toDateString();
    }

    public function vehicleType()
    {
        // Un mezzo appartiene a (belongsTo) un tipo
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Filtra i veicoli per il gruppo attivo dell'utente autenticato. Se non
     * c'è un utente autenticato (es. comandi da console), non applica
     * alcun filtro; se l'utente non ha un gruppo attivo, non restituisce
     * alcun risultato (vedi scopeForGroup()).
     */
    public function scopeForCurrentUser(Builder $query): Builder
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return $query;
        }

        return $query->forGroup($user->activeGroup()?->id);
    }

    /**
     * Filtra i veicoli per un gruppo esplicito, indipendentemente
     * dall'utente autenticato. Serve ai comandi da console (notifiche/report
     * schedulati) che iterano su più utenti senza un guard HTTP: lì
     * Auth::user() è sempre null, quindi scopeForCurrentUser() non
     * applicherebbe alcun filtro in quel caso (diverso da qui).
     *
     * Nessun gruppo (null) = nessun risultato, non "tutti i veicoli di
     * tutti i gruppi": ogni utente reale appartiene sempre ad almeno un
     * gruppo (creato già agganciato), quindi $groupId null qui significa
     * uno stato anomalo (es. rimosso dal suo unico gruppo), non un utente
     * "senza restrizioni". Prima del fix (audit sicurezza 2026-10-09),
     * un capo che rimuoveva un membro dal suo unico gruppo
     * (GroupController::removeMember) lasciava quel membro con accesso
     * illimitato a tutti i gruppi, invece che bloccato.
     */
    public function scopeForGroup(Builder $query, ?int $groupId): Builder
    {
        if ($groupId) {
            return $query->where('vehicles.group_id', $groupId);
        }

        return $query->whereRaw('1 = 0');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function carModel()
    {
        return $this->belongsTo(CarModel::class);
    }

    public function issues()
    {
        return $this->hasMany(Issue::class);
    }

    public function maintenanceRecords()
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    public function mileageLogs()
    {
        return $this->hasMany(MileageLog::class);
    }

    public function latestMileageLog()
    {
        return $this->hasOne(MileageLog::class)->latest('log_date');
    }

    /**
     * Ultimo appuntamento di lavaggio concluso (return_date valorizzato),
     * per mostrare la data accanto al veicolo nella lista (issue lavaggi).
     * Un lavaggio solo programmato non conta: stesso criterio già usato
     * per "Lavato questo mese" in dashboard (DashboardController).
     */
    public function lastCarWash()
    {
        return $this->hasOne(MaintenanceRecord::class)
            ->where('activity_type', MaintenanceRecord::ACTIVITY_WASH)
            ->whereNotNull('return_date')
            ->latest('return_date');
    }

    public function fleetDevices()
    {
        return $this->hasMany(FleetDevice::class);
    }

    public function deadlines()
    {
        return $this->hasMany(Deadline::class);
    }

    public function getMileageAttribute(): ?int
    {
        // Usa la relazione pre-caricata (latestMileageLog) se disponibile,
        // altrimenti fa query sull'ultimo log di chilometraggio.
        if ($this->relationLoaded('latestMileageLog')) {
            return $this->latestMileageLog?->mileage;
        }

        $latestLog = $this->mileageLogs()
            ->orderByDesc('log_date')
            ->first();

        return $latestLog?->mileage;
    }

    /**
     * Il log di chilometraggio precedente all'ultimo, usato per mostrare
     * la lettura precedente nell'index veicoli. Null se non esiste
     * uno storico (un solo log o nessuno).
     */
    public function getPreviousMileageLogAttribute(): ?MileageLog
    {
        if ($this->relationLoaded('mileageLogs')) {
            return $this->mileageLogs
                ->sortByDesc('log_date')
                ->values()
                ->get(1);
        }

        return $this->mileageLogs()
            ->orderByDesc('log_date')
            ->skip(1)
            ->first();
    }

    /**
     * Una scadenza per tipo, quella attiva (non rinnovata). Prima ordinava
     * per due_date decrescente, ma una cinghia a secco ha sempre due_date
     * nullo (scade solo a km, non a tempo): tra una scadenza già rinnovata
     * e quella attiva nata dal rinnovo, entrambe con due_date nullo,
     * l'ordinamento non riusciva a distinguerle e poteva scegliere quella
     * storica, facendo "sparire" la scadenza attiva dalla scheda veicolo.
     * is_renewed è il segnale affidabile di "superata", indipendente dal
     * tipo di scadenza avendo o meno una componente a tempo.
     */
    public function getDeadlinesGroupedAttribute(): Collection
    {
        return $this->deadlines
            ->sortBy(fn (Deadline $d) => [$d->is_renewed ? 1 : 0, $d->id * -1])
            ->groupBy('type')
            ->map(fn ($typeDeadlines) => $typeDeadlines->first());
    }

    public const DEADLINE_TYPES = [
        'revisione' => Deadline::TYPE_MINISTERIAL,
        'ossigeno' => Deadline::TYPE_OXYGEN,
        'cinghia' => Deadline::TYPE_CINGHIA,
        'tagliando' => Deadline::TYPE_TAGLIANDO,
    ];
}
