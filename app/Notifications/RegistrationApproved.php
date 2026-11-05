<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationApproved extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->subject('Your '.config('app.name').' account is approved')
            ->greeting('Assalamu alaikum,')
            ->line('An administrator has approved your registration. You can now sign in with the password you chose.')
            ->line('You will also receive a separate email asking you to verify your address.')
            ->action('Sign in', url('/login'));
    }
}
