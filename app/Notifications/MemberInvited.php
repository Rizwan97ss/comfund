<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberInvited extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

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
            ->subject('You are invited to '.config('app.name'))
            ->greeting('Assalamu alaikum,')
            ->line('You have been registered as a member of the community fund. Please activate your account using the link below.')
            ->line('You will be asked for the last four digits of your mobile number.')
            ->action('Activate my account', url('/invitation/'.$this->token))
            ->line('This link expires in '.config('fund.invitation_ttl_days').' days and can only be used once.')
            ->line('If you were not expecting this, you can ignore this email.');
    }
}
