<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ParkingPermitSuspendedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly CarbonInterface $until)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Parking Permit Suspended (2nd Offense)')
            ->greeting('Hello '.$notifiable->fullname.'!')
            ->line('The GSU approved the endorsement of your 2nd offense. Your campus parking permit is suspended for six (6) months.')
            ->line('Your vehicle cannot enter campus until '.ph_date($this->until, 'F j, Y').'.')
            ->line('If you believe this is incorrect, please contact the GSU office.')
            ->action('View my violations', route('user.violations'));
    }
}
