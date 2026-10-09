<?php

namespace App\Http\Controllers;

use App\Services\TelegramNotifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Riceve gli aggiornamenti dal bot Telegram (comando /start per
 * collegare l'account, /stop per scollegarlo). Nessuna sessione utente:
 * l'identità di chi scrive è solo il chat_id Telegram, verificato tramite
 * il token generato dal profilo (vedi TelegramNotifier::handleStart()).
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramNotifier $notifier): Response
    {
        // Fail-closed, non fail-open (audit sicurezza 2026-10-09): la rotta
        // è pubblica (nessun middleware auth), quindi senza un secret
        // configurato chiunque conoscesse l'URL potrebbe inviare
        // aggiornamenti falsi (es. forzare uno /stop su un chat_id
        // indovinato, o tentare /start su un token rubato). Se
        // TELEGRAM_WEBHOOK_SECRET non è impostato, la richiesta viene
        // sempre rifiutata invece di essere elaborata senza verifica.
        // hash_equals() invece di !== per un confronto a tempo costante.
        $secret = config('services.telegram.webhook_secret');
        if (! $secret || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token', ''))) {
            abort(403);
        }

        $chatId = $request->input('message.chat.id');
        $text = $request->input('message.text');

        if ($chatId !== null && $text !== null) {
            $notifier->handleIncomingMessage((string) $chatId, (string) $text);
        }

        // Telegram si aspetta solo un 200 OK: il contenuto della risposta
        // non viene letto, un codice diverso fa ritentare l'invio
        // dell'update più volte.
        return response()->noContent();
    }
}
