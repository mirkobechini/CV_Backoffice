<?php

namespace App\Observers;

use App\Models\Vehicle;
use App\Services\DashboardCache;

/**
 * Invalida la cache dashboard (5 minuti, vedi DashboardController) quando
 * cambia un dato che ci compare dentro: senza questo, una mutazione (es. il
 * rinnovo di una scadenza) restava invisibile in dashboard fino alla
 * scadenza naturale della cache, anche se già visibile ovunque altrove
 * nell'app.
 *
 * Registrato per tutti i modelli i cui dati alimentano la dashboard
 * (Deadline, Issue, MaintenanceRecord, Equipment, Tire, MileageLog,
 * Vehicle): vedi AppServiceProvider::boot().
 */
class DashboardCacheObserver
{
    public function saved(mixed $model): void
    {
        $this->forget($model);
    }

    public function deleted(mixed $model): void
    {
        $this->forget($model);
    }

    private function forget(mixed $model): void
    {
        $groupId = $model instanceof Vehicle
            ? $model->group_id
            : $model->vehicle?->group_id;

        DashboardCache::forgetForGroup($groupId);
    }
}
