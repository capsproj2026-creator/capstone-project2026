<?php

namespace App\Mail;

use App\Models\Visitor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VisitorPreRegisterMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Visitor $visitor)
    {
    }

    public function envelope(): Envelope
    {
        $code = (string) $this->visitor->confirmation_code;

        return new Envelope(
            subject: 'CSPC visit confirmation — '.$this->visitor->displayName().($code !== '' ? ' ('.$code.')' : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.visitor_pre_register',
        );
    }
}
