<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;

/**
 * Gate su una feature "premium" della licenza (es. Statistiche, backfill
 * AI). Vedi User::canUseLicenseFeature() per la logica fail-open.
 */
trait ChecksLicenseFeature
{
    protected function abortUnlessLicenseHasFeature(User $user, string $feature): void
    {
        if (! $user->canUseLicenseFeature($feature)) {
            abort(403, 'Questa funzionalità non è inclusa nella licenza del tuo gruppo.');
        }
    }
}
