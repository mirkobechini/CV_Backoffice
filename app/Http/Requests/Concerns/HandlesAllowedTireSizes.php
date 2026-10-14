<?php

namespace App\Http\Requests\Concerns;

use App\Models\Tire;

trait HandlesAllowedTireSizes
{
    /**
     * Regole condivise per allowed_tire_sizes (Store/UpdateVehicleRequest):
     * ogni misura consigliata ha una posizione (anteriore/posteriore/
     * entrambi), non solo la misura — alcune misure del libretto valgono
     * solo per un assale.
     *
     * @return array<string, mixed>
     */
    protected function allowedTireSizesRules(): array
    {
        return [
            'allowed_tire_sizes' => 'nullable|array',
            'allowed_tire_sizes.*.size' => ['nullable', 'string', 'max:255', 'regex:'.Tire::SIZE_REGEX],
            'allowed_tire_sizes.*.position' => 'nullable|in:front,rear,either',
        ];
    }

    protected function allowedTireSizesMessages(): array
    {
        return [
            'allowed_tire_sizes.*.size.regex' => 'La misura deve essere nel formato standard (es. 225/75R16C).',
            'allowed_tire_sizes.*.position.in' => 'La posizione selezionata non è valida.',
        ];
    }

    /**
     * Le righe rimosse/vuote del repeater inviano comunque un campo
     * nascosto vuoto: le scartiamo prima di validare, invece di farle
     * fallire come stringa vuota non valida. Posizione mancante o non
     * valida diventa "either" (nessuna restrizione), lo stesso
     * comportamento di una misura senza posizione specificata.
     */
    protected function normalizedAllowedTireSizes(): array
    {
        return collect($this->input('allowed_tire_sizes', []))
            ->filter(fn ($entry) => filled($entry['size'] ?? null))
            ->map(fn ($entry) => [
                'size' => $entry['size'],
                'position' => in_array($entry['position'] ?? null, ['front', 'rear', 'either'], true)
                    ? $entry['position']
                    : 'either',
            ])
            ->values()
            ->all();
    }
}
