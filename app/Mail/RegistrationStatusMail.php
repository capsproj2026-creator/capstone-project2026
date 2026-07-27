<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable sent when an admin approves or declines a vehicle registration request.
 */
class RegistrationStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $ownerName  Full name of the vehicle owner
     * @param  string  $status     'Approved' or 'Declined'
     * @param  string|null  $remarks  Optional admin reason (usually shown when declined)
     */
    public function __construct(
        public string $ownerName,
        public string $status,
        public ?string $remarks = null,
    ) {
    }

    /**
     * Subject line changes depending on whether the registration was approved or declined.
     */
    public function envelope(): Envelope
    {
        $subject = $this->status === 'Approved'
            ? 'Vehicle Registration Approved'
            : 'Vehicle Registration Declined';

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Renders the registration status email using our custom Blade template.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.registration_status',
        );
    }
}
