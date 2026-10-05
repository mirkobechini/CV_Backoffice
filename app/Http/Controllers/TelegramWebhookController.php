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
        // Senza questo controllo, chiunque conoscesse l'URL del webhook
        // potrebbe inviare aggiornamenti falsi (es. collegare un chat_id
        // a un codice rubato più facilmente, dato che il token da solo è
        // già una protezione debole essendo visibile nei log del bot).
        $secret = config('services.telegram.webhook_secret');
        if ($secret && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
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
