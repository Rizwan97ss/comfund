<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $reason) {}

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
            ->subject('Your '.config('app.name').' registration was not approved')
            ->greeting('Assalamu alaikum,')
            ->line('Your registration was not approved.')
            ->line('Reason: '.$this->reason)
            ->line('If you think this is a mistake, please contact an administrator.');
    }
}
