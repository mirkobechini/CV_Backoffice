<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Come HasVehicleGroupScoping, ma per i modelli con una colonna group_id
 * propria invece di dedurla da una relazione vehicle() (FleetDevice,
 * FuelCard): where('group_id', ...) era ripetuto 6 volte tra controller
 * (audit 2026-10-22, Q8).
 */
trait HasDirectGroupScoping
{
    public function scopeForCurrentUser(Builder $query): Builder
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return $query;
        }

        return $query->forGroup($user->activeGroup()?->id);
    }

    /**
     * Nessun gruppo (null) = nessun risultato, stesso principio di
     * Vehicle::scopeForGroup(): un utente reale appartiene sempre ad
     * almeno un gruppo, quindi null qui è uno stato anomalo da trattare
     * come "nessun accesso", non "accesso illimitato".
     */
    public function scopeForGroup(Builder $query, ?int $groupId): Builder
    {
        if ($groupId) {
            return $query->where('group_id', $groupId);
        }

        return $query->whereRaw('1 = 0');
    }
}
