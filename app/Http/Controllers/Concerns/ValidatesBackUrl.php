<?php

namespace App\Http\Controllers\Concerns;

/**
 * Il parametro `back` (dove tornare dopo un'azione, es. cancellazione) arriva
 * dalle viste come URL assoluto dello stesso sito (`url()->full()`) o come
 * path relativo — ma essendo comunque input utente va validato prima di un
 * redirect: un `back` che punta a un altro host (assoluto esterno, o
 * protocol-relative tipo `//evil.example`) reindirizzerebbe fuori dall'app
 * (open redirect, audit sicurezza SM4).
 */
trait ValidatesBackUrl
{
    /**
     * @return string|null `$back` se punta allo stesso host (path relativo
     *                     o URL assoluto same-origin), altrimenti null.
     */
    protected function safeBackUrl(?string $back): ?string
    {
        if (! $back) {
            return null;
        }

        if (str_starts_with($back, '/') && ! str_starts_with($back, '//')) {
            return $back;
        }

        if (str_starts_with($back, request()->getSchemeAndHttpHost().'/')) {
            return $back;
        }

        return null;
    }
}
