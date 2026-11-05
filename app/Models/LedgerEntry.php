<?php

namespace App\Models;

use App\Concerns\AuditsChanges;
use App\Concerns\ImmutableRecord;
use App\Enums\LedgerEntryKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A signed movement of money in one fund and one physical account. Each entry changes
 * both sides at once, so total funds always equal total money held in accounts.
 *
 * @property int $id
 * @property int $ledger_transaction_id
 * @property int $fund_id
 * @property int $financial_account_id
 * @property int|null $member_id
 * @property LedgerEntryKind $kind
 * @property int $amount_paise
 * @property Carbon $occurred_on
 */
#[Fillable(['ledger_transaction_id', 'fund_id', 'financial_account_id', 'member_id', 'kind', 'amount_paise', 'occurred_on'])]
class LedgerEntry extends Model
{
    use AuditsChanges, ImmutableRecord;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['kind' => LedgerEntryKind::class, 'amount_paise' => 'integer', 'occurred_on' => 'date:Y-m-d'];
    }

    public function auditCategory(): string
    {
        return 'finance';
    }

    /**
     * @return BelongsTo<LedgerTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
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
}
