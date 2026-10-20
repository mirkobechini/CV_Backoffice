<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $type;

    public $title;

    /**
     * Testo della notifica. NON chiamarla $message: Laravel riserva quel
     * nome nei dati passati alla vista (lo sovrascrive sempre con
     * l'istanza Illuminate\Mail\Message usata per gli allegati inline),
     * quindi il contenuto vero non arrivava mai alla vista — causa di un
     * crash in produzione ("htmlspecialchars(): ... Message given") ogni
     * volta che il comando generate-notifications provava a inviare
     * un'email di questo tipo.
     */
    public $body;

    public $url;

    /**
     * Create a new message instance.
     */
    public function __construct(string $type, string $title, string $body, ?string $url = null)
    {
        $this->type = $type;
        $this->title = $title;
        $this->body = $body;
        $this->url = $url;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🔔 {$this->title} - ".config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.event-notification',
            with: [
                'type' => $this->type,
                'title' => $this->title,
                'body' => $this->body,
                'url' => $this->url,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
