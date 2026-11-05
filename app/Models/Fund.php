<?php

namespace App\Models;

use App\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A charitable purpose that money is held for, independent of where it is physically kept.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_mandatory
 * @property bool $is_restricted
 * @property bool $is_active
 * @property int $sort_order
 */
class Fund extends Model
{
    use AuditsChanges;

    public const MONTHLY = 'monthly_fund';

    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean', 'is_restricted' => 'boolean', 'is_active' => 'boolean'];
    }

    public static function monthly(): self
    {
        return self::query()->where('code', self::MONTHLY)->firstOrFail();
    }

    public function isMonthly(): bool
    {
        return $this->code === self::MONTHLY;
    }

    /**
     * @return HasMany<FundPurpose, $this>
     */
    public function purposes(): HasMany
    {
        return $this->hasMany(FundPurpose::class);
    }
}
