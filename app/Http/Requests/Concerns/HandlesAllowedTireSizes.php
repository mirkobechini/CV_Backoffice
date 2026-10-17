<?php

namespace App\Http\Requests\Concerns;

use App\Models\Tire;
use Illuminate\Contracts\Validation\Validator;

trait HandlesAllowedTireSizes
{
    /**
     * Regole condivise per allowed_tire_sizes (Store/UpdateVehicleRequest):
     * ogni misura consigliata ha un codice breve (es. "A1"), lo stesso
     * usato dalla carta di circolazione per indicarla nella tabella delle
     * combinazioni ammesse — vedi allowedTireCombinationsRules() sotto.
     *
     * @return array<string, mixed>
     */
    protected function allowedTireSizesRules(): array
    {
        return [
            'allowed_tire_sizes' => 'nullable|array',
            'allowed_tire_sizes.*.size' => ['nullable', 'string', 'max:255', 'regex:'.Tire::SIZE_REGEX],
            'allowed_tire_sizes.*.code' => 'nullable|string|max:10',
            'allowed_tire_size_combinations' => 'nullable|array',
            'allowed_tire_size_combinations.*.front_code' => 'nullable|string|max:10',
            'allowed_tire_size_combinations.*.rear_code' => 'nullable|string|max:10',
        ];
    }

    protected function allowedTireSizesMessages(): array
    {
        return [
            'allowed_tire_sizes.*.size.regex' => 'La misura deve essere nel formato standard (es. 225/75R16C).',
        ];
    }

    /**
     * Le righe rimosse/vuote del repeater inviano comunque un campo
     * nascosto vuoto: le scartiamo prima di validare, invece di farle
     * fallire come stringa vuota non valida. Una misura senza codice
     * riceve un codice sequenziale di fallback ("1", "2", ...) invece di
     * essere scartata — una misura con un valore reale non deve MAI
     * sparire al salvataggio solo perché il codice non è compilato
     * (bug segnalato 2026-10-17: un salvataggio che non toccava le
     * misure le cancellava silenziosamente per questo motivo). Stesso
     * criterio di fallback già usato dalla migrazione dati per lo storico.
     */
    protected function normalizedAllowedTireSizes(): array
    {
        return collect($this->input('allowed_tire_sizes', []))
            ->filter(fn ($entry) => filled($entry['size'] ?? null))
            ->values()
            ->map(fn ($entry, $index) => [
                'size' => $entry['size'],
                'code' => filled($entry['code'] ?? null) ? $entry['code'] : (string) ($index + 1),
            ])
            ->all();
    }

    /**
     * Righe senza entrambi i codici scartate, come sopra. Non filtra per
     * codici esistenti: quello è un controllo di validazione (vedi
     * assertAllowedTireCombinationsReferenceKnownCodes()), non una
     * normalizzazione silenziosa — referenziare un codice inesistente è un
     * errore dell'utente da segnalare, non da far sparire senza avviso.
     */
    protected function normalizedAllowedTireCombinations(): array
    {
        return collect($this->input('allowed_tire_size_combinations', []))
            ->filter(fn ($entry) => filled($entry['front_code'] ?? null) && filled($entry['rear_code'] ?? null))
            ->map(fn ($entry) => [
                'front_code' => $entry['front_code'],
                'rear_code' => $entry['rear_code'],
            ])
            ->values()
            ->all();
    }

    /**
     * Una combinazione non può referenziare un codice non definito tra le
     * misure consigliate — altrimenti Tire::sizeMismatchWarning() non
     * potrebbe mai risolverla. Va chiamato da withValidator() DOPO che
     * prepareForValidation() ha già normalizzato entrambi i campi.
     */
    protected function assertAllowedTireCombinationsReferenceKnownCodes(Validator $validator): void
    {
        $knownCodes = collect($this->input('allowed_tire_sizes', []))->pluck('code')->filter()->unique();

        collect($this->input('allowed_tire_size_combinations', []))->each(function ($entry, $index) use ($validator, $knownCodes) {
            if (! $knownCodes->contains($entry['front_code'] ?? null)) {
                $validator->errors()->add("allowed_tire_size_combinations.{$index}.front_code", 'Il codice anteriore non corrisponde a nessuna misura consigliata.');
            }
            if (! $knownCodes->contains($entry['rear_code'] ?? null)) {
                $validator->errors()->add("allowed_tire_size_combinations.{$index}.rear_code", 'Il codice posteriore non corrisponde a nessuna misura consigliata.');
            }
        });
    }
}
