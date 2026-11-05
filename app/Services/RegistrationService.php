<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Exceptions\FinancialRuleViolation;
use App\Models\User;
use App\Notifications\RegistrationApproved;
use App\Notifications\RegistrationReceived;
use App\Notifications\RegistrationRejected;
use Illuminate\Support\Facades\Notification;

/**
 * Self-registration → admin review → activation. A pending account cannot sign in,
 * owes no dues and cannot have money recorded against it until an admin approves it.
 */
class RegistrationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly DuesService $dues,
    ) {}

    /**
     * @param  array<string, mixed>  $data  name, email, phone, address, password
     */
    public function register(array $data): User
    {
        $member = $this->audit->atomic(function () use ($data): User {
            $member = new User($data);
            $member->forceFill([
                'role' => UserRole::Member,
                'status' => AccountStatus::Pending,
            ])->save();

            return $member;
        });

        Notification::send(
            User::query()->where('role', UserRole::Admin)->where('status', AccountStatus::Active)->get(),
            new RegistrationReceived($member->id, $member->name),
        );

        return $member;
    }

    /**
     * The joining date is set here because it decides which months of dues are owed.
     *
     * @param  array{joined_on: string, monthly_contribution_paise?: int|null}  $terms
     */
    public function approve(User $member, array $terms): User
    {
        $member = $this->audit->atomic(function () use ($member, $terms): User {
            $member = $this->lockPending($member);

            $member->forceFill([
                'status' => AccountStatus::Active,
                'activated_at' => now(),
                'member_code' => sprintf('MCF-%05d', $member->id),
                'joined_on' => $terms['joined_on'],
                'monthly_contribution_paise' => $terms['monthly_contribution_paise'] ?? null,
            ])->save();

            $this->dues->generateArrears($member);

            return $member;
        });

        $member->notify(new RegistrationApproved);

        // The address is still unproven, and the account stays locked until it is verified.
        // A mail failure must not undo the approval: they can ask for the link again after signing in.
        rescue(fn () => $member->sendEmailVerificationNotification());

        return $member;
    }

    /**
     * The account is removed, so the person may apply again. The audit log keeps the record.
     */
    public function reject(User $member, string $reason): void
    {
        $email = $member->email;

        $this->audit->atomic(function () use ($member, $reason): void {
            $member = $this->lockPending($member);

            $this->audit->record(
                action: 'users.registration_rejected',
                category: $member->auditCategory(),
                severity: 'warning',
                subject: $member,
                payload: ['reason' => $reason],
            );

            $member->delete();
        });

        Notification::route('mail', $email)->notify(new RegistrationRejected($reason));
    }

    private function lockPending(User $member): User
    {
        $member = User::query()->lockForUpdate()->findOrFail($member->id);

        if ($member->status !== AccountStatus::Pending) {
            throw FinancialRuleViolation::conflict('This registration has already been reviewed.');
        }

        return $member;
    }
}
