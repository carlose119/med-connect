<?php

namespace App\Mail;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationActivated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $doctorUser,
        public string $invitationToken,
        public Carbon $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu cuenta de médico ha sido creada — MedConnect',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.doctor-invitation',
            with: [
                'doctorName' => $this->doctorUser->name,
                'email' => $this->doctorUser->email,
                'invitationToken' => $this->invitationToken,
                'expiresAt' => $this->expiresAt,
                'activationUrl' => url("/invitation/{$this->invitationToken}"),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}