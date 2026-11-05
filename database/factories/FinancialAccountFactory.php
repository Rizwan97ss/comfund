<?php

namespace Database\Factories;

use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinancialAccount>
 */
class FinancialAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Account',
            'type' => FinancialAccountType::Bank,
            'is_active' => true,
        ];
    }
}
