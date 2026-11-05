<?php

namespace App\Models;

use App\Concerns\AuditsChanges;
use App\Enums\PaymentMethod;
use App\Enums\SubmissionSource;
use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $member_id
 * @property int $fund_id
 * @property int $amount_paise
 * @property Carbon|null $applicable_month
 * @property Carbon $payment_date
 * @property PaymentMethod $payment_method
 * @property string|null $transaction_reference
 * @property string|null $duplicate_guard
 * @property string|null $member_note
 * @property int|null $document_id
 * @property SubmissionSource $source
 * @property string|null $attribution_note
 * @property int $submitted_by
 * @property int|null $resubmission_of_id
 * @property string $idempotency_key
 * @property SubmissionStatus $status
 * @property int|null $financial_account_id
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property int|null $ledger_transaction_id
 * @property Carbon|null $created_at
 */
#[Fillable([
    'uuid', 'member_id', 'fund_id', 'amount_paise', 'applicable_month', 'payment_date', 'payment_method',
    'transaction_reference', 'duplicate_guard', 'member_note', 'document_id', 'source', 'attribution_note',
    'submitted_by', 'resubmission_of_id', 'idempotency_key', 'status', 'financial_account_id',
    'reviewed_by', 'reviewed_at', 'rejection_reason', 'ledger_transaction_id',
])]
class PaymentSubmission extends Model
{
    use AuditsChanges;

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'applicable_month' => 'date:Y-m-d',
            'payment_date' => 'date:Y-m-d',
            'payment_method' => PaymentMethod::class,
            'source' => SubmissionSource::class,
            'status' => SubmissionStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function auditCategory(): string
    {
        return 'finance';
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isPending(): bool
    {
        return $this->status === SubmissionStatus::Pending;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    /**
     * @return BelongsTo<Fund, $this>
     */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    /**
     * @return BelongsTo<LedgerTransaction, $this>
     */
    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }

    /**
     * @return BelongsTo<PaymentSubmission, $this>
     */
    public function resubmissionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resubmission_of_id');
    }
}
