<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UnitInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $bodyText
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'دعوت‌نامه واحد در Buildino'
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.unit-invitation'
        );
    }
}
