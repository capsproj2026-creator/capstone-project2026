<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ViolationRecordedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $violationType,
        private readonly int $strikeCount,
        private readonly bool $accountLocked,
        private readonly ?string $description = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Campus Violation Notice')
            ->greeting('Hello '.$notifiable->fullname.'!')
            ->line('A new violation has been recorded on your account.');

        if ($this->description) {
            $mail->line('Violation details: '.$this->description);
        }

        $mail->line('Violation type: '.$this->violationType)
            ->line('Total strikes: '.$this->strikeCount.'/'.($notifiable::MAX_STRIKES ?? 3).'.');

        if ($this->accountLocked) {
            $mail->line('Your account has reached '.($notifiable::MAX_STRIKES ?? 3).' strikes and is now locked.');
        }

        return $mail
            ->action('View your account', route('login'))
            ->line('If you believe this is incorrect, please contact campus administration immediately.');
    }
}
