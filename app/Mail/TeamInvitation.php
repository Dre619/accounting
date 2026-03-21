<?php

namespace App\Mail;

use App\Models\CompanyInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly CompanyInvitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to join {$this->invitation->company->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.team-invitation',
        );
    }
}
