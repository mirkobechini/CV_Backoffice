<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Un solo messaggio con tutti gli eventi di un giro di
 * GenerateNotifications, invece di un'email separata per ogni evento — una
 * raffica di N email slegate tra loro per lo stesso run era rumorosa e
 * difficile da seguire, stesso problema già corretto per Telegram
 * (TelegramNotifier::sendDigest(), audit UX 2026-11-26) e segnalato da
 * Mirko anche per l'email. Sostituisce EventNotificationMail (un'email per
 * evento), rimasta senza altri usi dopo questo cambio.
 */
class NotificationDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{type: string, title: string, body: string, url: ?string}>  $events
     */
    public function __construct(public array $events) {}

    public function envelope(): Envelope
    {
        $count = count($this->events);
        $subject = $count === 1
            ? "🔔 {$this->events[0]['title']} - ".config('app.name')
            : "🔔 {$count} nuove notifiche - ".config('app.name');

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification-digest',
            with: ['events' => $this->events],
        );
    }
}
