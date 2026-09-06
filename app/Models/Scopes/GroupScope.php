<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Filtra i veicoli per il gruppo dell'utente autenticato.
 *
 * Ogni utente vede solo i veicoli del proprio gruppo. Se l'utente non è
 * autenticato (es. comandi artisan, job) o non appartiene a un gruppo,
 * non viene applicato alcun filtro per non rompere le operazioni di sistema.
 */
class GroupScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (!$user) {
            return;
        }

        $groupId = $user->activeGroup()?->id;

        if ($groupId) {
            $builder->where($model->getTable() . '.group_id', $groupId);
        }
    }
}
