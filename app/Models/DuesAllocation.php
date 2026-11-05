<?php

namespace App\Models;

use App\Concerns\AuditsChanges;
use App\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money applied to (positive) or withdrawn from (negative) a month's dues.
 *
 * @property int $id
 * @property int $dues_obligation_id
 * @property int|null $ledger_transaction_id
 * @property int $amount_paise
 * @property string $reason
 * @property int|null $created_by
 */
#[Fillable(['dues_obligation_id', 'ledger_transaction_id', 'amount_paise', 'reason', 'created_by'])]
class DuesAllocation extends Model
{
    use AuditsChanges, ImmutableRecord;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['amount_paise' => 'integer'];
    }

    public function auditCategory(): string
    {
        return 'finance';
    }

    /**
     * @return BelongsTo<DuesObligation, $this>
     */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(DuesObligation::class, 'dues_obligation_id');
    }
}
