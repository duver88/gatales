<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SubscriptionRenewedMail extends Mailable
{

    public User $user;
    public int $tokensBalance;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, int $tokensBalance)
    {
        $this->user = $user;
        $this->tokensBalance = $tokensBalance;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu suscripcion ha sido renovada - Tus tokens estan listos',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-renewed',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
