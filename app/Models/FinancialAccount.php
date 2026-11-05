<?php

namespace App\Models;

use App\Concerns\AuditsChanges;
use App\Enums\FinancialAccountType;
use Database\Factories\FinancialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A physical place money is kept: cash in hand, a bank account, a digital wallet.
 *
 * @property int $id
 * @property string $name
 * @property FinancialAccountType $type
 * @property string|null $account_reference
 * @property bool $is_active
 */
#[Fillable(['name', 'type', 'account_reference', 'is_active'])]
class FinancialAccount extends Model
{
    /** @use HasFactory<FinancialAccountFactory> */
    use AuditsChanges, HasFactory;

    protected function casts(): array
    {
        return ['type' => FinancialAccountType::class, 'is_active' => 'boolean'];
    }

    public function auditCategory(): string
    {
        return 'finance';
    }
}
