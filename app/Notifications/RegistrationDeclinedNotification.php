<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationDeclinedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $roleName,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Registration Was Declined')
            ->greeting('Hello '.$notifiable->fullname.'!')
            ->line('After reviewing your registration, we were unable to approve your account at this time.')
            ->line('Registration type: '.$this->roleName)
            ->line('If you believe this decision is in error, please contact campus administration and verify your submitted details.')
            ->action('Contact Administration', route('login'))
            ->line('Thank you for your interest in the Smart Campus Vehicle Management System.');
    }
}
