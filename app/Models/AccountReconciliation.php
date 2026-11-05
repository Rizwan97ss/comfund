<?php

namespace App\Models;

use App\Concerns\AuditsChanges;
use App\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A recorded comparison of a real bank statement or cash count against the books.
 *
 * @property int $id
 * @property int $financial_account_id
 * @property Carbon $as_of
 * @property int $statement_balance_paise
 * @property int $book_balance_paise
 * @property string|null $note
 * @property int $created_by
 * @property Carbon|null $created_at
 */
#[Fillable(['financial_account_id', 'as_of', 'statement_balance_paise', 'book_balance_paise', 'note', 'created_by'])]
class AccountReconciliation extends Model
{
    use AuditsChanges, ImmutableRecord;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['as_of' => 'date:Y-m-d', 'statement_balance_paise' => 'integer', 'book_balance_paise' => 'integer'];
    }

    public function auditCategory(): string
    {
        return 'finance';
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
}
