<?php

namespace App\Models;

use App\Concerns\AuditsChanges;
use App\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * What one member owes for one month. The amount is a snapshot, so later rate
 * changes never alter what was already due.
 *
 * @property int $id
 * @property int $member_id
 * @property int $fund_id
 * @property Carbon $period
 * @property int $amount_paise
 * @property string $rate_source
 * @property int|null $allocations_sum_amount_paise
 * @property int|null $waivers_sum_amount_paise
 */
#[Fillable(['member_id', 'fund_id', 'period', 'amount_paise', 'rate_source'])]
class DuesObligation extends Model
{
    use AuditsChanges, ImmutableRecord;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['amount_paise' => 'integer', 'period' => 'date:Y-m-d'];
    }

    public function auditCategory(): string
    {
        return 'finance';
    }

    /**
     * @param  Builder<DuesObligation>  $query
     * @return Builder<DuesObligation>
     */
    public function scopeWithSettlement(Builder $query): Builder
    {
        return $query->withSum('allocations', 'amount_paise')->withSum('waivers', 'amount_paise');
    }

    public function paidPaise(): int
    {
        return (int) $this->allocations_sum_amount_paise;
    }

    public function waivedPaise(): int
    {
        return (int) $this->waivers_sum_amount_paise;
    }

    public function outstandingPaise(): int
    {
        return max(0, $this->amount_paise - $this->paidPaise() - $this->waivedPaise());
    }

    public function settlementStatus(): string
    {
        $paid = $this->paidPaise();
        $waived = $this->waivedPaise();

        return match (true) {
            $this->outstandingPaise() === 0 && $paid === 0 => 'waived',
            $this->outstandingPaise() === 0 => 'paid',
            $paid > 0 || $waived > 0 => 'partial',
            default => 'unpaid',
        };
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    /**
     * @return HasMany<DuesAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(DuesAllocation::class);
    }

    /**
     * @return HasMany<DuesWaiver, $this>
     */
    public function waivers(): HasMany
    {
        return $this->hasMany(DuesWaiver::class);
    }
}
