<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Member,
            'status' => AccountStatus::Active,
            'phone' => '9'.fake()->unique()->numerify('#########'),
            'address' => fake()->address(),
            'joined_on' => now()->subYear()->startOfMonth()->format('Y-m-d'),
            'activated_at' => now(),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Admin, 'joined_on' => null]);
    }

    /**
     * A member joined in the current month, with no arrears to account for.
     */
    public function joinedThisMonth(): static
    {
        return $this->state(fn (array $attributes) => ['joined_on' => now('Asia/Kolkata')->startOfMonth()->format('Y-m-d')]);
    }

    public function invited(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AccountStatus::Invited,
            'password' => null,
            'email_verified_at' => null,
            'activated_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => AccountStatus::Suspended, 'suspended_at' => now()]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => ['email_verified_at' => null]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
