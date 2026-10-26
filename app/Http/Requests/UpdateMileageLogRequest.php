<?php

namespace App\Http\Requests;

/**
 * Identica a StoreMileageLogRequest tranne per il controllo di conflitto
 * cronologico, che deve escludere il record corrente (audit 2026-10-22,
 * Q6): prima duplicata per intero invece di estendere.
 */
class UpdateMileageLogRequest extends StoreMileageLogRequest
{
    protected function currentMileageLogId(): ?int
    {
        return $this->route('mileageLog')?->id;
    }
}
