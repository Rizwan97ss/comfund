<?php

namespace App\Models;

use App\Concerns\AuditsChanges;
use App\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An uploaded receipt or supporting document, stored privately under a random name.
 *
 * @property int $id
 * @property string $uuid
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property int $uploaded_by
 */
#[Fillable(['uuid', 'path', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'uploaded_by'])]
#[Hidden(['path'])]
class Document extends Model
{
    use AuditsChanges, ImmutableRecord;

    public const UPDATED_AT = null;

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasOne<PaymentSubmission, $this>
     */
    public function submission(): HasOne
    {
        return $this->hasOne(PaymentSubmission::class);
    }

    /**
     * @return BelongsToMany<Expense, $this>
     */
    public function expenses(): BelongsToMany
    {
        return $this->belongsToMany(Expense::class, 'expense_documents');
    }
}
