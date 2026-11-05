<?php

namespace App\Models;

use App\Concerns\AuditsChanges;
use App\Concerns\ImmutableRecord;
use App\Enums\LedgerTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property LedgerTransactionType $type
 * @property string|null $idempotency_key
 * @property int|null $reverses_transaction_id
 * @property int|null $member_id
 * @property Carbon $occurred_on
 * @property string $narration
 * @property int|null $posted_by
 * @property Carbon|null $created_at
 */
#[Fillable(['uuid', 'type', 'idempotency_key', 'reverses_transaction_id', 'member_id', 'occurred_on', 'narration', 'posted_by'])]
class LedgerTransaction extends Model
{
    use AuditsChanges, ImmutableRecord;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['type' => LedgerTransactionType::class, 'occurred_on' => 'date:Y-m-d'];
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
     * @return HasMany<LedgerEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * The transaction that reversed this one, if any.
     *
     * @return HasOne<LedgerTransaction, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_transaction_id');
    }

    /**
     * @return BelongsTo<LedgerTransaction, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_transaction_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }
}
