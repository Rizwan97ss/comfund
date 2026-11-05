<?php

namespace App\Models;

use App\Concerns\AuditsChanges;
use App\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $dues_obligation_id
 * @property int $amount_paise
 * @property string $reason
 * @property int|null $reverses_waiver_id
 * @property int $granted_by
 * @property Carbon|null $created_at
 */
#[Fillable(['dues_obligation_id', 'amount_paise', 'reason', 'reverses_waiver_id', 'granted_by'])]
class DuesWaiver extends Model
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * @return HasOne<DuesWaiver, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_waiver_id');
    }
}
