<?php

namespace App\Services;

use App\Enums\LedgerEntryKind;
use App\Enums\LedgerTransactionType;
use App\Enums\PaymentMethod;
use App\Enums\SubmissionSource;
use App\Enums\SubmissionStatus;
use App\Exceptions\FinancialRuleViolation;
use App\Models\Document;
use App\Models\FinancialAccount;
use App\Models\Fund;
use App\Models\LedgerTransaction;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Notifications\SubmissionReviewed;
use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class SubmissionService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly LedgerService $ledger,
        private readonly DuesService $dues,
        private readonly DocumentService $documents,
    ) {}

    /**
     * Record a member's payment as pending. It has no effect on any balance until approved.
     *
     * @param  array<string, mixed>  $data  fund_id, amount_paise, applicable_month, payment_date, payment_method, transaction_reference, member_note, resubmission_of
     */
    public function submit(User $member, array $data, ?UploadedFile $receipt, string $idempotencyKey): PaymentSubmission
    {
        if (($existing = $this->replay($idempotencyKey, $member)) !== null) {
            return $existing;
        }

        $resubmissionOfId = $this->resolveResubmission($member, isset($data['resubmission_of']) ? (string) $data['resubmission_of'] : null);
        $document = null;

        try {
            return $this->audit->atomic(function () use ($member, $data, $receipt, $idempotencyKey, $resubmissionOfId, &$document) {
                $document = $receipt === null ? null : $this->documents->store($receipt, $member);

                return $this->createSubmission($member, $member, $data, $document, $idempotencyKey, SubmissionStatus::Pending, SubmissionSource::Member, null, $resubmissionOfId);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $this->documents->discard($document);

            return $this->replay($idempotencyKey, $member) ?? throw $this->duplicateReference();
        } catch (\Throwable $exception) {
            $this->documents->discard($document);

            throw $exception;
        }
    }

    /**
     * Approve exactly once: the row lock and status check stop concurrent double approval.
     */
    public function approve(PaymentSubmission $submission, User $admin, FinancialAccount $account): PaymentSubmission
    {
        $approved = $this->audit->atomic(function () use ($submission, $admin, $account) {
            $locked = PaymentSubmission::query()->lockForUpdate()->findOrFail($submission->id);

            if (! $locked->isPending()) {
                throw FinancialRuleViolation::conflict('This submission has already been reviewed.');
            }

            $this->assertUsableAccount($account);

            return $this->postAndMarkApproved($locked, $admin, $account);
        });

        $approved->member->notify(new SubmissionReviewed($approved));

        return $approved;
    }

    public function reject(PaymentSubmission $submission, User $admin, string $reason): PaymentSubmission
    {
        $rejected = $this->audit->atomic(function () use ($submission, $admin, $reason) {
            $locked = PaymentSubmission::query()->lockForUpdate()->findOrFail($submission->id);

            if (! $locked->isPending()) {
                throw FinancialRuleViolation::conflict('This submission has already been reviewed.');
            }

            $locked->forceFill([
                'status' => SubmissionStatus::Rejected,
                'rejection_reason' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                // Release the reference so a corrected payment can be resubmitted with it.
                'duplicate_guard' => null,
            ])->save();

            return $locked;
        });

        $rejected->member->notify(new SubmissionReviewed($rejected));

        return $rejected;
    }

    /**
     * An admin records a payment on behalf of a member. Attribution is mandatory and it
     * is confirmed immediately, because the admin is the one who received the money.
     *
     * @param  array<string, mixed>  $data  member_id, fund_id, amount_paise, applicable_month, payment_date, payment_method, transaction_reference, attribution_note, financial_account_id
     */
    public function recordManualEntry(User $admin, array $data, ?UploadedFile $receipt, string $idempotencyKey): PaymentSubmission
    {
        if (($existing = $this->replay($idempotencyKey, $admin)) !== null) {
            return $existing;
        }

        $member = User::query()->members()->findOrFail((int) $data['member_id']);
        $account = FinancialAccount::query()->findOrFail((int) $data['financial_account_id']);
        $document = null;

        try {
            return $this->audit->atomic(function () use ($admin, $member, $account, $data, $receipt, $idempotencyKey, &$document) {
                $this->assertUsableAccount($account);
                $document = $receipt === null ? null : $this->documents->store($receipt, $admin);

                $submission = $this->createSubmission(
                    $member, $admin, $data, $document, $idempotencyKey,
                    SubmissionStatus::Pending, SubmissionSource::AdminManual, (string) $data['attribution_note'], null,
                );

                $locked = PaymentSubmission::query()->lockForUpdate()->findOrFail($submission->id);
                $locked->wasRecentlyCreated = true;

                return $this->postAndMarkApproved($locked, $admin, $account);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $this->documents->discard($document);

            return $this->replay($idempotencyKey, $admin) ?? throw $this->duplicateReference();
        } catch (\Throwable $exception) {
            $this->documents->discard($document);

            throw $exception;
        }
    }

    /**
     * Reverse a confirmed transaction of any type, and undo its effect on the member's dues.
     */
    public function reverseTransaction(LedgerTransaction $transaction, string $reason, User $admin): LedgerTransaction
    {
        return $this->audit->atomic(function () use ($transaction, $reason, $admin) {
            $reversal = $this->ledger->reverse($transaction, BusinessCalendar::today(), $reason, $admin);

            if ($transaction->type === LedgerTransactionType::Contribution && $transaction->member_id !== null) {
                $this->dues->rebalanceAfterReversal($transaction->member()->firstOrFail(), $reversal->id, $admin->id);
            }

            return $reversal;
        });
    }

    private function postAndMarkApproved(PaymentSubmission $locked, User $admin, FinancialAccount $account): PaymentSubmission
    {
        $fund = $locked->fund;
        $member = $locked->member;

        $transaction = $this->ledger->post(
            type: LedgerTransactionType::Contribution,
            lines: [[
                'fund_id' => $fund->id,
                'account_id' => $account->id,
                'kind' => LedgerEntryKind::Collection,
                'amount_paise' => $locked->amount_paise,
            ]],
            occurredOn: $locked->payment_date,
            narration: "{$fund->name} from {$member->name}",
            postedBy: $admin,
            memberId: $member->id,
            idempotencyKey: "submission:{$locked->id}",
        );

        $locked->forceFill([
            'status' => SubmissionStatus::Approved,
            'financial_account_id' => $account->id,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'ledger_transaction_id' => $transaction->id,
        ])->save();

        if ($fund->isMonthly()) {
            $preferred = $locked->applicable_month === null ? null : CarbonImmutable::parse($locked->applicable_month);
            $this->dues->allocateAvailable($member, $preferred, $transaction->id, $admin->id);
        }

        return $locked;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createSubmission(
        User $member,
        User $submitter,
        array $data,
        ?Document $document,
        string $idempotencyKey,
        SubmissionStatus $status,
        SubmissionSource $source,
        ?string $attribution,
        ?int $resubmissionOfId,
    ): PaymentSubmission {
        $fund = Fund::query()->where('is_active', true)->findOrFail((int) $data['fund_id']);
        $method = PaymentMethod::from((string) $data['payment_method']);
        $reference = $this->normaliseReference(isset($data['transaction_reference']) ? (string) $data['transaction_reference'] : null);
        $guard = $reference === null ? null : hash('sha256', $method->value.'|'.Str::upper($reference));

        if ($guard !== null && PaymentSubmission::query()->where('duplicate_guard', $guard)->exists()) {
            throw $this->duplicateReference();
        }

        return PaymentSubmission::query()->forceCreate([
            'uuid' => (string) Str::uuid(),
            'member_id' => $member->id,
            'fund_id' => $fund->id,
            'amount_paise' => $data['amount_paise'],
            'applicable_month' => $fund->isMonthly() ? BusinessCalendar::month((string) $data['applicable_month'])->format('Y-m-d') : null,
            'payment_date' => $data['payment_date'],
            'payment_method' => $method,
            'transaction_reference' => $reference,
            'duplicate_guard' => $guard,
            'member_note' => $data['member_note'] ?? null,
            'document_id' => $document?->id,
            'source' => $source,
            'attribution_note' => $attribution,
            'submitted_by' => $submitter->id,
            'resubmission_of_id' => $resubmissionOfId,
            'idempotency_key' => $idempotencyKey,
            'status' => $status,
        ])->refresh();
    }

    /**
     * @phpstan-impure
     */
    private function replay(string $idempotencyKey, User $submitter): ?PaymentSubmission
    {
        $existing = PaymentSubmission::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null && $existing->submitted_by !== $submitter->id) {
            throw FinancialRuleViolation::conflict('This request key has already been used.');
        }

        return $existing;
    }

    private function resolveResubmission(User $member, ?string $uuid): ?int
    {
        if ($uuid === null) {
            return null;
        }

        $original = PaymentSubmission::query()
            ->where('uuid', $uuid)
            ->where('member_id', $member->id)
            ->where('status', SubmissionStatus::Rejected)
            ->first();

        if ($original === null) {
            throw new FinancialRuleViolation('Only your own rejected payments can be resubmitted.');
        }

        return $original->id;
    }

    private function assertUsableAccount(FinancialAccount $account): void
    {
        if (! $account->is_active) {
            throw new FinancialRuleViolation('This account is no longer active.');
        }
    }

    private function normaliseReference(?string $reference): ?string
    {
        $reference = $reference === null ? null : trim($reference);

        return $reference === '' ? null : $reference;
    }

    private function duplicateReference(): FinancialRuleViolation
    {
        return new FinancialRuleViolation('A payment with this method and transaction reference has already been recorded.');
    }
}
