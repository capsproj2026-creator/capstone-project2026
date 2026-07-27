<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationApprovedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Account Registration Is Approved')
            ->greeting('Hello '.$notifiable->fullname.'!')
            ->line('Your registration for the campus Vehicle Management System has been approved.')
            ->action('Sign in to your account', route('login'))
            ->line('Thank you for using Smart Campus VMS.');
    }
}
