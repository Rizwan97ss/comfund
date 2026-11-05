<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Exceptions\FinancialRuleViolation;
use App\Models\MemberInvitation;
use App\Models\User;
use App\Notifications\MemberInvited;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Invitation → verification → profile → password → activation. Only a hash of the
 * token is stored, and each step can only be reached after the one before it.
 */
class InvitationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly DuesService $dues,
    ) {}

    /**
     * @param  array<string, mixed>  $data  name, email, phone, address, joined_on, monthly_contribution_paise
     */
    public function registerMember(array $data, User $admin): User
    {
        [$member, $token] = $this->audit->atomic(function () use ($data, $admin) {
            $member = new User($data);
            $member->forceFill([
                'role' => UserRole::Member,
                'status' => AccountStatus::Invited,
                'invited_by' => $admin->id,
            ])->save();

            $member->forceFill(['member_code' => sprintf('MCF-%05d', $member->id)])->save();
            $this->dues->generateArrears($member);

            return [$member, $this->issue($member, $admin)];
        });

        $member->notify(new MemberInvited($token));

        return $member;
    }

    /**
     * Revoke any outstanding invitation and send a new one.
     */
    public function resend(User $member, User $admin): void
    {
        if ($member->status !== AccountStatus::Invited) {
            throw FinancialRuleViolation::conflict('This member has already activated their account.');
        }

        $token = $this->audit->atomic(fn (): string => $this->issue($member, $admin));

        $member->notify(new MemberInvited($token));
    }

    public function findUsable(string $token): MemberInvitation
    {
        $invitation = MemberInvitation::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($invitation === null || ! $invitation->isUsable()) {
            throw new NotFoundHttpException('This invitation is invalid or has expired.');
        }

        return $invitation;
    }

    /**
     * Account verification: the link proves control of the email, and the last four
     * digits of the mobile number on file prove it reached the intended person.
     */
    public function verify(MemberInvitation $invitation, string $phoneLastFour): void
    {
        $phone = (string) $invitation->user->phone;

        if (! hash_equals(substr($phone, -4), $phoneLastFour)) {
            $attempts = $invitation->failed_attempts + 1;
            $locked = $attempts >= (int) config('fund.invitation_max_failed_attempts');

            $invitation->forceFill([
                'failed_attempts' => $attempts,
                'revoked_at' => $locked ? now() : null,
            ])->save();

            throw new FinancialRuleViolation($locked
                ? 'Too many incorrect attempts. Please ask an administrator for a new invitation.'
                : 'That does not match the mobile number on file.');
        }

        if ($invitation->verified_at === null) {
            $invitation->forceFill(['verified_at' => now()])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $profile  name, phone, address
     */
    public function completeProfile(MemberInvitation $invitation, array $profile): void
    {
        if ($invitation->verified_at === null) {
            throw FinancialRuleViolation::conflict('Please verify your account first.');
        }

        $this->audit->atomic(function () use ($invitation, $profile) {
            $invitation->user->fill($profile)->save();
            $invitation->forceFill(['profile_completed_at' => now()])->save();
        });
    }

    public function activate(MemberInvitation $invitation, string $password): User
    {
        if ($invitation->verified_at === null || $invitation->profile_completed_at === null) {
            throw FinancialRuleViolation::conflict('Please complete the earlier steps first.');
        }

        return $this->audit->atomic(function () use ($invitation, $password) {
            $user = $invitation->user;

            $user->forceFill([
                'password' => $password,
                'status' => AccountStatus::Active,
                'activated_at' => now(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });
    }

    private function issue(User $member, User $admin): string
    {
        MemberInvitation::query()
            ->where('user_id', $member->id)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->get()
            ->each(fn (MemberInvitation $old) => $old->forceFill(['revoked_at' => now()])->save());

        $token = Str::random(64);

        MemberInvitation::query()->create([
            'user_id' => $member->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays((int) config('fund.invitation_ttl_days')),
            'sent_by' => $admin->id,
        ]);

        return $token;
    }
}
