<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DoctorAccountCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $doctorUser,
        public string $tempPassword,
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
            view: 'emails.doctor-account-created',
            with: [
                'doctorName' => $this->doctorUser->name,
                'email' => $this->doctorUser->email,
                'tempPassword' => $this->tempPassword,
                'loginUrl' => url('/doctor/login'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}