<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\InvestmentType;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investment>
 */
class InvestmentFactory extends Factory
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
            'type' => InvestmentType::Stock,
            'name' => fake()->company(),
            'ticker' => fake()->lexify('????'),
            'currency' => Currency::BRL,
            'current_price' => fake()->randomFloat(4, 5, 200),
        ];
    }

    /**
     * @return Factory<Investment>
     */
    public function fixedIncome(): Factory
    {
        return $this->state(fn () => [
            'type' => InvestmentType::FixedIncome,
            'ticker' => null,
            'currency' => Currency::BRL,
            'current_price' => null,
        ]);
    }

    /**
     * @return Factory<Investment>
     */
    public function treasury(): Factory
    {
        return $this->state(fn () => [
            'type' => InvestmentType::Treasury,
            'currency' => Currency::BRL,
        ]);
    }

    /**
     * @return Factory<Investment>
     */
    public function pension(): Factory
    {
        return $this->state(fn () => [
            'type' => InvestmentType::Pension,
            'ticker' => null,
            'currency' => Currency::BRL,
        ]);
    }
}
