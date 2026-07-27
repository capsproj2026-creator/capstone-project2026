<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable = a Laravel class that packages an email (subject, view, data).
 * This one is sent when a guard records a traffic/parking violation.
 */
class VehicleViolationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Public properties are automatically passed to the Blade view.
     * We expose plate number, violation type, and description so the email template can display them.
     */
    public function __construct(
        public string $plateNumber,
        public string $violationType,
        public ?string $description = null,
    ) {
    }

    /**
     * Envelope defines the email "wrapper" — mainly the subject line.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'ALERT: Campus Traffic / Parking Violation',
        );
    }

    /**
     * Content tells Laravel which Blade view to render for the HTML body.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.violation',
        );
    }
}
