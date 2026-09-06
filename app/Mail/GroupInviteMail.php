<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GroupInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public $groupName;

    public $inviteCode;

    /**
     * Create a new message instance.
     */
    public function __construct(string $groupName, string $inviteCode)
    {
        $this->groupName = $groupName;
        $this->inviteCode = $inviteCode;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invito a entrare nel gruppo {$this->groupName} - CV Backoffice",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.group-invite',
            with: [
                'groupName' => $this->groupName,
                'inviteCode' => $this->inviteCode,
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
