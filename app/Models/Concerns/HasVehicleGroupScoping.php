<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Filtra per il gruppo del veicolo collegato tramite la relazione vehicle():
 * whereHas('vehicle', fn ($q) => $q->forCurrentUser()/forGroup($id)) era
 * copiato identico ~60 volte in 26 file (audit 2026-10-22, Q5). Applicabile
 * a ogni modello con una relazione vehicle() (Issue, Deadline,
 * MaintenanceRecord, MileageLog, FuelLog, Tire, Equipment).
 */
trait HasVehicleGroupScoping
{
    public function scopeForCurrentUser(Builder $query): Builder
    {
        return $query->whereHas('vehicle', fn ($q) => $q->forCurrentUser());
    }

    public function scopeForGroup(Builder $query, ?int $groupId): Builder
    {
        return $query->whereHas('vehicle', fn ($q) => $q->forGroup($groupId));
    }
}
