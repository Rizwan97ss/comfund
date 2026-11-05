<?php

namespace App\Models;

use App\Concerns\AuditsChanges;
use App\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $amount_paise
 * @property Carbon $effective_from
 * @property string|null $note
 * @property int|null $created_by
 */
#[Fillable(['amount_paise', 'effective_from', 'note', 'created_by'])]
class MonthlyRate extends Model
{
    use AuditsChanges, ImmutableRecord;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['amount_paise' => 'integer', 'effective_from' => 'date:Y-m-d'];
    }

    public function auditCategory(): string
    {
        return 'finance';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
