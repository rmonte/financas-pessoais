<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bank_id' => null,
            'name' => fake()->words(2, true),
            'currency' => Currency::BRL,
            'description' => fake()->optional()->sentence(),
            'initial_balance' => fake()->randomFloat(2, 0, 5000),
            'is_active' => true,
            'is_credit_card' => false,
            'credit_limit' => null,
            'closing_day' => null,
            'due_day' => null,
        ];
    }

    /**
     * @return Factory<Account>
     */
    public function creditCard(): Factory
    {
        return $this->state(fn () => [
            'is_credit_card' => true,
            'credit_limit' => fake()->randomFloat(2, 1000, 10000),
            'closing_day' => fake()->numberBetween(1, 28),
            'due_day' => fake()->numberBetween(1, 28),
        ]);
    }

    /**
     * @return Factory<Account>
     */
    public function usd(): Factory
    {
        return $this->state(fn () => ['currency' => Currency::USD]);
    }
}
