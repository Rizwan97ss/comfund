<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DuesReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $outstandingPaise) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Monthly fund reminder')
            ->greeting('Assalamu alaikum,')
            ->line('Your outstanding monthly fund balance is ₹'.number_format($this->outstandingPaise / 100, 2).'.')
            ->action('View my dues', url('/dues'));
    }
}
