<?php

namespace App\Notifications;

use App\Enums\SubmissionStatus;
use App\Models\PaymentSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubmissionReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly PaymentSubmission $submission) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = '₹'.number_format($this->submission->amount_paise / 100, 2);
        $fund = $this->submission->fund->name;

        if ($this->submission->status === SubmissionStatus::Approved) {
            return (new MailMessage)
                ->subject("Payment approved: {$amount}")
                ->greeting('Assalamu alaikum,')
                ->line("Your {$fund} payment of {$amount} has been approved. JazakAllah khair.")
                ->action('View my contributions', url('/contributions'));
        }

        return (new MailMessage)
            ->subject("Payment not approved: {$amount}")
            ->greeting('Assalamu alaikum,')
            ->line("Your {$fund} payment of {$amount} was not approved.")
            ->line('Reason: '.$this->submission->rejection_reason)
            ->line('You can correct the details and submit it again.')
            ->action('View my contributions', url('/contributions'));
    }
}
