<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationSettingRequest;
use App\Models\NotificationSetting;
use App\Services\TelegramNotifier;
use Illuminate\Http\RedirectResponse;

class NotificationSettingController extends Controller
{
    /**
     * Chiavi delle impostazioni testuali/numeriche (le altre sono booleane).
     */
    private const TEXT_KEYS = ['report_email', 'report_frequency', 'reminder_days_before'];

    /**
     * Chiavi delle impostazioni booleane (checkbox "notifica su...").
     */
    private const BOOLEAN_KEYS = ['notify_on_maintenance', 'notify_on_deadline', 'notify_on_issue', 'notify_on_equipment', 'notify_on_tire_season'];

    /**
     * Show the form for editing the current user's notification settings.
     *
     * Le impostazioni sono personali per account, non condivise dal gruppo.
     */
    public function edit(TelegramNotifier $telegram)
    {
        $settings = auth()->user()->notificationSettings()->pluck('value', 'key');

        return view('admin.notifications.edit', compact('settings') + [
            'telegramConfigured' => $telegram->isConfigured(),
            'telegramLinked' => $telegram->isLinked(auth()->user()),
            'telegramBotUsername' => $telegram->botUsername(),
        ]);
    }

    /**
     * Genera un nuovo codice di collegamento Telegram (vedi
     * TelegramNotifier::generateLinkToken()) e lo mostra una volta sola:
     * l'utente lo invia al bot con /start CODICE per collegare l'account.
     */
    public function telegramLink(TelegramNotifier $telegram): RedirectResponse
    {
        $token = $telegram->generateLinkToken(auth()->user());

        return redirect()->route('admin.notifications.edit')
            ->with('status', 'telegram-link-generated')
            ->with('telegramLinkToken', $token);
    }

    public function telegramUnlink(TelegramNotifier $telegram): RedirectResponse
    {
        $telegram->unlink(auth()->user());

        return redirect()->route('admin.notifications.edit')
            ->with('status', 'telegram-unlinked');
    }

    public function update(UpdateNotificationSettingRequest $request)
    {
        $data = $request->validated();
        $userId = auth()->id();

        foreach (self::TEXT_KEYS as $key) {
            NotificationSetting::updateOrCreate(
                ['user_id' => $userId, 'key' => $key],
                ['value' => $data[$key]]
            );
        }

        // I checkbox non compilati non vengono inviati dal browser: vanno
        // letti esplicitamente con boolean() per poterli salvare come "false"
        // (altrimenti disattivare una notifica non avrebbe mai effetto).
        foreach (self::BOOLEAN_KEYS as $key) {
            NotificationSetting::updateOrCreate(
                ['user_id' => $userId, 'key' => $key],
                ['value' => $request->boolean($key) ? '1' : '0']
            );
        }

        return redirect()->route('admin.notifications.edit')
            ->with('status', 'Impostazioni aggiornate con successo.');
    }
}
