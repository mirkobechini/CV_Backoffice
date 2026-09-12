<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Vehicle extends Model
{
    use LogsActivity, Searchable, SoftDeletes;

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
        'vehicle_type_id',
        'immatricolation_date',
        'registration_card_path',
        'warranty_expiration_date',
        'has_warranty_extension',
        'warranty_extension_duration',
        'has_timing_belt',
    ];

    protected $casts = [
        'immatricolation_date' => 'date',
        'warranty_expiration_date' => 'date',
        'has_timing_belt' => 'boolean',
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
     * Filtra i veicoli per il gruppo dell'utente autenticato.
     * Se l'utente non ha un gruppo, non applica alcun filtro.
     */
    public function scopeForCurrentUser(Builder $query): Builder
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return $query;
        }

        $groupId = $user->activeGroup()?->id;

        if ($groupId) {
            return $query->where('vehicles.group_id', $groupId);
        }

        return $query;
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

    public function equipment()
    {
        return $this->hasMany(Equipment::class);
    }

    public function deadlines()
    {
        return $this->hasMany(Deadline::class);
    }

    public function hasAllRequiredEquipment(): bool
    {
        return $this->missingRequiredEquipment()->isEmpty();
    }

    public function missingRequiredEquipment()
    {
        $this->loadMissing(['equipment', 'vehicleType.equipmentTypes']);

        $requiredEquipmentTypes = $this->vehicleType?->equipmentTypes ?? collect();

        if ($requiredEquipmentTypes->isEmpty()) {
            return collect();
        }

        // Raggruppa gli equipaggiamenti già presenti sul veicolo per equipment_type_id
        // e calcola quante unità abbiamo per ciascun tipo.
        $availableQuantities = $this->equipment
            ->groupBy('equipment_type_id')
            ->map(fn ($items) => $items->count());

        // Restituisce solo i tipi di equipaggiamento per cui la quantità disponibile
        // sul veicolo è inferiore alla quantità richiesta dal pivot required_quantity.
        return $requiredEquipmentTypes->filter(function ($equipmentType) use ($availableQuantities) {
            $requiredQuantity = (int) $equipmentType->pivot->required_quantity;
            $actualQuantity = (int) ($availableQuantities[$equipmentType->id] ?? 0);

            return $actualQuantity < $requiredQuantity;
        });
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

    public function getDeadlinesGroupedAttribute(): Collection
    {
        return $this->deadlines
            ->sortByDesc('due_date')
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
