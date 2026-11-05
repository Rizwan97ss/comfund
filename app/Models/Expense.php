<?php

namespace App\Models;

use App\Concerns\AuditsChanges;
use App\Concerns\ImmutableRecord;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $fund_id
 * @property int $financial_account_id
 * @property int $amount_paise
 * @property string $beneficiary
 * @property string $purpose
 * @property Carbon $expense_date
 * @property PaymentMethod $payment_method
 * @property string|null $transaction_reference
 * @property string|null $description
 * @property int $created_by
 * @property int $ledger_transaction_id
 * @property string $idempotency_key
 */
#[Fillable([
    'uuid', 'fund_id', 'financial_account_id', 'amount_paise', 'beneficiary', 'purpose', 'expense_date',
    'payment_method', 'transaction_reference', 'description', 'created_by', 'ledger_transaction_id', 'idempotency_key',
])]
class Expense extends Model
{
    use AuditsChanges, ImmutableRecord;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['amount_paise' => 'integer', 'expense_date' => 'date:Y-m-d', 'payment_method' => PaymentMethod::class];
    }

    public function auditCategory(): string
    {
        return 'finance';
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Fund, $this>
     */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<LedgerTransaction, $this>
     */
    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }

    /**
     * @return BelongsToMany<Document, $this>
     */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'expense_documents');
    }
}
