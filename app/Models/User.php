<?php

namespace App\Models;

use App\Concerns\AuditsChanges;
use App\Enums\AccountStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property UserRole $role
 * @property AccountStatus $status
 * @property string|null $member_code
 * @property string|null $phone
 * @property string|null $address
 * @property Carbon|null $joined_on
 * @property int|null $monthly_contribution_paise
 * @property int|null $invited_by
 * @property Carbon|null $activated_at
 * @property Carbon|null $suspended_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'phone', 'address', 'joined_on', 'monthly_contribution_paise'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use AuditsChanges, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'status' => AccountStatus::class,
            'address' => 'encrypted',
            'joined_on' => 'date:Y-m-d',
            'monthly_contribution_paise' => 'integer',
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isMember(): bool
    {
        return $this->role === UserRole::Member;
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeMembers(Builder $query): Builder
    {
        return $query->where('role', UserRole::Member);
    }

    /**
     * @return HasMany<PaymentSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(PaymentSubmission::class, 'member_id');
    }

    /**
     * @return HasMany<DuesObligation, $this>
     */
    public function obligations(): HasMany
    {
        return $this->hasMany(DuesObligation::class, 'member_id');
    }

    /**
     * @return HasMany<MemberInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(MemberInvitation::class);
    }
}
