<?php

namespace App\Services;

use App\Exceptions\IssuePartClassificationException;
use App\Models\Issue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Suggerisce la parte del veicolo coinvolta (Issue::PARTS) per i guasti
 * esistenti rimasti senza, leggendo la sola descrizione testuale — usata
 * dalla pagina di revisione (IssuePartBackfillController), mai per
 * assegnare automaticamente senza conferma dell'utente.
 */
class IssuePartClassifierService
{
    /**
     * Quante descrizioni per chiamata: un numero alto rischia di superare
     * il contesto del modello o di perdere accuratezza, uno basso moltiplica
     * inutilmente le chiamate (costo/tempo) per liste lunghe di guasti.
     */
    private const BATCH_SIZE = 25;

    private const SYSTEM_PROMPT_TEMPLATE = <<<'PROMPT'
Sei un assistente che classifica la descrizione di un guasto su un veicolo
(ambulanza/mezzo di soccorso) nella parte del VEICOLO più probabilmente
coinvolta, scegliendo UNA SOLA opzione tra questo elenco fisso (testuale
esatto, mai altre parole): %s

Importante: classifica solo parti del veicolo (motore, freni, carrozzeria,
impianto elettrico, ecc.), MAI attrezzature o dispositivi medicali (barella,
bombola ossigeno, defibrillatore, estintore...), che sono guasti di un altro
tipo e non rientrano in questo elenco: se la descrizione riguarda chiaramente
un'attrezzatura e non il veicolo, rispondi null.

Ricevi un array JSON di oggetti {"id": int, "description": string}.
Rispondi SOLO con un oggetto JSON {"classifications": [{"id": int, "part": string|null}, ...]},
uno per ogni guasto ricevuto, nello stesso ordine, stessi id. Usa null anche
se la descrizione è troppo vaga o generica per scegliere con ragionevole
sicurezza (es. "controllo generale", "manutenzione"). Il valore di "part"
deve essere ESATTAMENTE una delle stringhe dell'elenco sopra, altrimenti null.
PROMPT;

    public function __construct(
        private ?string $apiKey = null,
        private ?string $model = null,
        private ?string $baseUrl = null,
    ) {
        $this->apiKey ??= config('services.openrouter.key');
        $this->model ??= config('services.openrouter.model');
        $this->baseUrl ??= config('services.openrouter.base_url');
    }

    /**
     * @param  Collection<int, Issue>  $issues
     * @return array<int, ?string> issue id => parte suggerita (null se l'AI non è sicura)
     *
     * @throws IssuePartClassificationException
     */
    public function suggestParts(Collection $issues): array
    {
        if ($issues->isEmpty()) {
            return [];
        }

        if (! $this->apiKey) {
            throw new IssuePartClassificationException('Nessuna chiave API configurata per la classificazione dei guasti.');
        }

        $suggestions = [];

        foreach ($issues->chunk(self::BATCH_SIZE) as $batch) {
            $suggestions += $this->classifyBatch($batch);
        }

        return $suggestions;
    }

    /**
     * @param  Collection<int, Issue>  $batch
     * @return array<int, ?string>
     */
    private function classifyBatch(Collection $batch): array
    {
        $payload = $batch->map(fn (Issue $issue) => [
            'id' => $issue->id,
            'description' => $issue->description,
        ])->values()->all();

        $systemPrompt = sprintf(self::SYSTEM_PROMPT_TEMPLATE, implode(', ', Issue::PARTS));

        $response = Http::withToken($this->apiKey)
            ->timeout(30)
            ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                'model' => $this->model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => json_encode($payload)],
                ],
            ]);

        if (! $response->successful()) {
            throw new IssuePartClassificationException('Chiamata al servizio di classificazione fallita: '.$response->status());
        }

        $content = $response->json('choices.0.message.content');
        $decoded = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($decoded) || ! is_array($decoded['classifications'] ?? null)) {
            throw new IssuePartClassificationException('Risposta del servizio di classificazione non valida.');
        }

        $suggestions = [];
        foreach ($decoded['classifications'] as $item) {
            $id = $item['id'] ?? null;

            if (! is_int($id)) {
                continue;
            }

            $part = $item['part'] ?? null;
            $suggestions[$id] = in_array($part, Issue::PARTS, true) ? $part : null;
        }

        return $suggestions;
    }
}
