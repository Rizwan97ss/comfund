<?php

namespace App\Models;

use App\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Only the SHA-256 of the invitation token is stored.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $profile_completed_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 * @property int $failed_attempts
 * @property int $sent_by
 */
#[Fillable(['user_id', 'token_hash', 'expires_at', 'verified_at', 'profile_completed_at', 'accepted_at', 'revoked_at', 'failed_attempts', 'sent_by'])]
class MemberInvitation extends Model
{
    use AuditsChanges;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'profile_completed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'failed_attempts' => 'integer',
        ];
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
