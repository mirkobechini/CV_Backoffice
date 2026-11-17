<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Le API REST (`Api\*Controller`) leggevano `per_page` dalla richiesta senza
 * alcun tetto massimo: un client poteva chiedere `per_page=999999` e forzare
 * una query enorme (DoS, audit sicurezza SM7).
 */
trait CapsPerPage
{
    protected function perPage(Request $request, int $default = 20, int $max = 100): int
    {
        $perPage = (int) ($request->per_page ?: $default);

        return max(1, min($perPage, $max));
    }
}
