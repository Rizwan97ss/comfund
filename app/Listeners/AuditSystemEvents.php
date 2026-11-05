<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\CurrentDeviceLogout;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Events\Dispatcher;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;

/**
 * Sends authentication, mail, scheduler and queue events to the audit log.
 */
class AuditSystemEvents
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'onLogin',
            Failed::class => 'onFailedLogin',
            Logout::class => 'onLogout',
            CurrentDeviceLogout::class => 'onDeviceLogout',
            OtherDeviceLogout::class => 'onDeviceLogout',
            Lockout::class => 'onLockout',
            PasswordReset::class => 'onPasswordReset',
            Verified::class => 'onEmailVerified',
            PasswordUpdatedViaController::class => 'onPasswordUpdated',
            RecoveryCodeReplaced::class => 'onTwoFactorEvent',
            RecoveryCodesGenerated::class => 'onTwoFactorEvent',
            TwoFactorAuthenticationChallenged::class => 'onTwoFactorEvent',
            TwoFactorAuthenticationConfirmed::class => 'onTwoFactorEvent',
            TwoFactorAuthenticationDisabled::class => 'onTwoFactorEvent',
            TwoFactorAuthenticationEnabled::class => 'onTwoFactorEvent',
            TwoFactorAuthenticationFailed::class => 'onTwoFactorEvent',
            ValidTwoFactorAuthenticationCodeProvided::class => 'onTwoFactorEvent',
            MessageSent::class => 'onMailSent',
            JobFailed::class => 'onJobFailed',
            ScheduledTaskFinished::class => 'onScheduledTask',
            ScheduledTaskFailed::class => 'onScheduledTask',
        ];
    }

    public function onLogin(Login $event): void
    {
        $this->audit->record('auth.login', 'security', actor: $this->user($event->user), payload: ['remember' => $event->remember]);
    }

    public function onFailedLogin(Failed $event): void
    {
        $this->audit->record('auth.login_failed', 'security', 'warning', payload: [
            'attempted_email' => $event->credentials['email'] ?? null,
            'known_account' => $event->user !== null,
        ]);
    }

    public function onLogout(Logout $event): void
    {
        $this->audit->record('auth.logout', 'security', actor: $this->user($event->user));
    }

    public function onDeviceLogout(CurrentDeviceLogout|OtherDeviceLogout $event): void
    {
        $this->audit->record('auth.'.($event instanceof OtherDeviceLogout ? 'other_devices_logged_out' : 'device_logged_out'), 'security', actor: $this->user($event->user));
    }

    public function onLockout(Lockout $event): void
    {
        $this->audit->record('auth.lockout', 'security', 'warning', payload: ['attempted_email' => $event->request->input('email')]);
    }

    public function onPasswordReset(PasswordReset $event): void
    {
        $this->audit->record('auth.password_reset', 'security', 'notice', actor: $this->user($event->user));
    }

    public function onEmailVerified(Verified $event): void
    {
        $this->audit->record('auth.email_verified', 'security', actor: $event->user instanceof User ? $event->user : null);
    }

    public function onPasswordUpdated(PasswordUpdatedViaController $event): void
    {
        $this->audit->record('auth.password_changed', 'security', 'notice', actor: $this->user($event->user));
    }

    public function onTwoFactorEvent(object $event): void
    {
        $action = (string) preg_replace('/([a-z])([A-Z])/', '$1_$2', class_basename($event));
        $isFailure = $event instanceof TwoFactorAuthenticationFailed;

        $this->audit->record(
            'auth.'.strtolower($action),
            'security',
            $isFailure ? 'warning' : 'info',
            actor: isset($event->user) ? $this->user($event->user) : null,
        );
    }

    public function onMailSent(MessageSent $event): void
    {
        $message = $event->sent->getOriginalMessage();

        $this->audit->record('mail.sent', 'system', payload: [
            'to' => array_map(fn ($address): string => $address->getAddress(), $event->message->getTo()),
            'subject' => method_exists($message, 'getSubject') ? $message->getSubject() : null,
        ]);
    }

    public function onJobFailed(JobFailed $event): void
    {
        $this->audit->record('queue.job_failed', 'system', 'error', payload: [
            'job' => $event->job->resolveName(),
            'error' => class_basename($event->exception),
        ]);
    }

    public function onScheduledTask(ScheduledTaskFinished|ScheduledTaskFailed $event): void
    {
        $failed = $event instanceof ScheduledTaskFailed;

        $this->audit->record($failed ? 'scheduler.task_failed' : 'scheduler.task_finished', 'system', $failed ? 'error' : 'info', payload: [
            'task' => $event->task->getSummaryForDisplay(),
        ]);
    }

    private function user(mixed $user): ?User
    {
        return $user instanceof User ? $user : null;
    }
}
