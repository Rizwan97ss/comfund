<?php

namespace App\Models;

use App\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Running balance of one fund in one account. Derived from the ledger, kept so the
 * row can be locked while spending is checked. `ledger:reconcile` proves it matches.
 *
 * @property int $id
 * @property int $fund_id
 * @property int $financial_account_id
 * @property int $balance_paise
 */
#[Fillable(['fund_id', 'financial_account_id', 'balance_paise'])]
class FundAccountBalance extends Model
{
    use AuditsChanges;

    protected function casts(): array
    {
        return ['balance_paise' => 'integer'];
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
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }
}
