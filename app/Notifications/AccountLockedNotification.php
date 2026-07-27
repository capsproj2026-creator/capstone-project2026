<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountLockedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly int $strikeCount)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Account Locked due to 3 Strikes')
            ->greeting('Hello '.$notifiable->fullname.'!')
            ->line('Your account has reached '.$this->strikeCount.' strikes and is now locked.')
            ->line('If you believe this is incorrect, please contact campus administration immediately.')
            ->action('Contact Support', route('login'));
    }
}
