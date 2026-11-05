<?php

namespace App\Models;

use App\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A purpose a restricted fund may lawfully be spent on.
 *
 * @property int $id
 * @property int $fund_id
 * @property string $name
 * @property bool $is_active
 */
#[Fillable(['fund_id', 'name', 'is_active'])]
class FundPurpose extends Model
{
    use AuditsChanges;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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
}
