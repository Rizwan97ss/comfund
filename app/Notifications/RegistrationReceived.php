<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Takes plain values, because the account may be rejected and deleted before this is sent.
     */
    public function __construct(
        private readonly int $memberId,
        private readonly string $memberName,
    ) {}

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
            ->subject('New member registration: '.$this->memberName)
            ->greeting('Assalamu alaikum,')
            ->line($this->memberName.' has registered and is waiting for approval.')
            ->action('Review registration', url('/admin/members/'.$this->memberId));
    }
}
