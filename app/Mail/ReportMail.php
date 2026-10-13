<?php

namespace App\Mail;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    /**
     * Create a new message instance.
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '📋 Report giornaliero - CV Backoffice',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.report',
            with: ['data' => $this->data],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = $this->renderPdf();

        return [
            Attachment::fromData(fn () => $pdf, 'report-'.now()->format('Y-m-d').'.pdf')
                ->withMime('application/pdf'),
        ];
    }

    /**
     * Genera il PDF del report. Estratto da attachments() per poterlo
     * riusare anche per l'invio via Telegram (SendSummaryReport), che non
     * passa dal Mailer.
     */
    public function renderPdf(): string
    {
        return Pdf::setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true])
            ->loadView('pdfs.report', ['data' => $this->data])
            ->output();
    }
}
