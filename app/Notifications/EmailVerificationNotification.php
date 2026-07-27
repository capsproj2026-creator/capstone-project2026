<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $recipientName = property_exists($notifiable, 'fullname') ? $notifiable->fullname : 'Applicant';
        $emailAddress = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('mail')
            : ($notifiable->email ?? '');

        return (new MailMessage)
            ->subject('Verify Your Email Address')
            ->greeting('Hello '.$recipientName.'!')
            ->line('Thank you for registering for the Smart Campus Vehicle Management System.')
            ->line('Please verify your email address before signing in.')
            ->line('Your verification code is:')
            ->line('**'.$this->code.'**')
            ->action('Verify Email', route('register', ['email' => $emailAddress]))
            ->line('This code will expire in 24 hours.')
            ->line('If you did not request this verification, please ignore this email.');
    }
}
