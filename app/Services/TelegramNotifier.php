<?php

namespace App\Services;

use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Notifiche via bot Telegram, in aggiunta all'email esistente
 * (GenerateNotifications). Collegamento account tramite comando /start
 * con un token generato dal profilo (vedi TelegramLinkController),
 * perché un bot Telegram non ha modo di sapere "chi" gli scrive se non
 * glielo si dice esplicitamente la prima volta.
 */
class TelegramNotifier
{
    private const LINK_TOKEN_TTL_MINUTES = 15;

    public function botUsername(): ?string
    {
        return config('services.telegram.bot_username');
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.telegram.bot_token');
    }

    /**
     * Genera un token di collegamento valido per 15 minuti, da inviare al
     * bot con "/start {token}". Sovrascrive un eventuale token precedente
     * non ancora usato.
     */
    public function generateLinkToken(User $user): string
    {
        $token = Str::upper(Str::random(8));

        NotificationSetting::updateOrCreate(
            ['user_id' => $user->id, 'key' => 'telegram_link_token'],
            ['value' => $token.'|'.now()->addMinutes(self::LINK_TOKEN_TTL_MINUTES)->timestamp]
        );

        return $token;
    }

    public function isLinked(User $user): bool
    {
        return (bool) $user->notificationSetting('telegram_chat_id');
    }

    public function unlink(User $user): void
    {
        NotificationSetting::where('user_id', $user->id)
            ->whereIn('key', ['telegram_chat_id', 'telegram_link_token'])
            ->delete();
    }

    /**
     * Gestisce un aggiornamento in arrivo dal webhook Telegram: solo i
     * comandi /start {token} e /stop sono supportati, qualsiasi altro
     * messaggio viene ignorato silenziosamente (il bot non è conversazionale).
     */
    public function handleIncomingMessage(string $chatId, string $text): void
    {
        $text = trim($text);

        if (Str::startsWith($text, '/start')) {
            $this->handleStart($chatId, trim(Str::after($text, '/start')));

            return;
        }

        if ($text === '/stop') {
            $this->handleStop($chatId);
        }
    }

    private function handleStart(string $chatId, string $token): void
    {
        if ($token === '') {
            $this->sendRaw($chatId, 'Per collegare questo account, genera un codice dalla pagina del tuo profilo su '.config('app.name').' e invialo qui con /start CODICE.');

            return;
        }

        // Confronto esatto in PHP, non LIKE: il token arriva dal testo del
        // messaggio Telegram, quindi un attaccante poteva inserire `%`/`_`
        // (wildcard SQL non escapati) per far corrispondere il token
        // pendente di un ALTRO utente con pochi tentativi (audit sicurezza
        // 2026-10-22, SH7). La tabella ha al più un record per utente, il
        // costo di caricarla tutta è trascurabile.
        $token = strtoupper($token);
        $setting = NotificationSetting::where('key', 'telegram_link_token')
            ->get()
            ->first(fn (NotificationSetting $s) => Str::before($s->value, '|') === $token);

        if (! $setting) {
            $this->sendRaw($chatId, 'Codice non valido o già usato. Generane uno nuovo dal profilo.');

            return;
        }

        [, $expiresAt] = explode('|', $setting->value);

        if ((int) $expiresAt < now()->timestamp) {
            $setting->delete();
            $this->sendRaw($chatId, 'Codice scaduto. Generane uno nuovo dal profilo.');

            return;
        }

        $userId = $setting->user_id;
        $setting->delete();

        NotificationSetting::updateOrCreate(
            ['user_id' => $userId, 'key' => 'telegram_chat_id'],
            ['value' => $chatId]
        );

        $this->sendRaw($chatId, 'Account collegato. Riceverai qui le notifiche di '.config('app.name').'. Invia /stop per disattivarle.');
    }

    private function handleStop(string $chatId): void
    {
        $setting = NotificationSetting::where('key', 'telegram_chat_id')->where('value', $chatId)->first();

        if ($setting) {
            $setting->delete();
        }

        $this->sendRaw($chatId, 'Notifiche disattivate. Puoi ricollegarti in qualsiasi momento dal profilo.');
    }

    /**
     * Invia una notifica di evento a un utente, se ha collegato Telegram.
     */
    public function send(User $user, string $title, ?string $message = null): bool
    {
        $chatId = $user->notificationSetting('telegram_chat_id');

        if (! $chatId) {
            return false;
        }

        return $this->sendRaw($chatId, $message ? "{$title}\n{$message}" : $title);
    }

    /**
     * Invia un file (es. il PDF del report riassuntivo) a un utente, se ha
     * collegato Telegram.
     */
    public function sendDocument(User $user, string $filename, string $contents, ?string $caption = null): bool
    {
        $chatId = $user->notificationSetting('telegram_chat_id');

        if (! $chatId || ! $this->isConfigured()) {
            return false;
        }

        $response = Http::attach('document', $contents, $filename)
            ->post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/sendDocument', array_filter([
                'chat_id' => $chatId,
                'caption' => $caption,
            ]));

        if ($response->failed()) {
            Log::warning("Telegram: invio documento fallito per chat {$chatId}: {$response->body()}");
        }

        return $response->successful();
    }

    private function sendRaw(string $chatId, string $text): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $response = Http::post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        if ($response->failed()) {
            Log::warning("Telegram: invio messaggio fallito per chat {$chatId}: {$response->body()}");
        }

        return $response->successful();
    }
}
