<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Chiave e invalidazione della cache dashboard (vedi DashboardController),
 * centralizzate qui così sia il controller sia chi invalida la cache dopo
 * una mutazione (DashboardCacheObserver) restano allineati sulla stessa
 * chiave per gruppo.
 */
class DashboardCache
{
    public static function key(?int $groupId): string
    {
        return 'dashboard.stats.' . ($groupId ?? 'none');
    }

    public static function forgetForGroup(?int $groupId): void
    {
        Cache::forget(self::key($groupId));
    }
}
