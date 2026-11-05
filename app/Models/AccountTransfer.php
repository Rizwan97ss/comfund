<?php

namespace App\Models;

use App\Concerns\AuditsChanges;
use App\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money moved between physical accounts within one fund. Neither income nor expense.
 *
 * @property int $id
 * @property string $uuid
 * @property int $fund_id
 * @property int $from_account_id
 * @property int $to_account_id
 * @property int $amount_paise
 * @property Carbon $transfer_date
 * @property string|null $transaction_reference
 * @property string|null $note
 * @property int $created_by
 * @property int $ledger_transaction_id
 * @property string $idempotency_key
 */
#[Fillable(['uuid', 'fund_id', 'from_account_id', 'to_account_id', 'amount_paise', 'transfer_date', 'transaction_reference', 'note', 'created_by', 'ledger_transaction_id', 'idempotency_key'])]
class AccountTransfer extends Model
{
    use AuditsChanges, ImmutableRecord;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['amount_paise' => 'integer', 'transfer_date' => 'date:Y-m-d'];
    }

    public function auditCategory(): string
    {
        return 'finance';
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
    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'from_account_id');
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'to_account_id');
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
}
