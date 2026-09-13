<?php

namespace Database\Factories;

use App\Enums\InvestmentOperationType;
use App\Models\Investment;
use App\Models\InvestmentOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentOperation>
 */
class InvestmentOperationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(6, 1, 50);
        $unitPrice = fake()->randomFloat(4, 5, 200);

        return [
            'user_id' => User::factory(),
            'investment_id' => fn (array $attributes) => Investment::factory()->create([
                'user_id' => $attributes['user_id'],
            ])->id,
            'type' => InvestmentOperationType::Buy,
            'date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => round($quantity * $unitPrice, 2),
            'description' => fake()->optional()->sentence(),
        ];
    }

    /**
     * @return Factory<InvestmentOperation>
     */
    public function sell(): Factory
    {
        return $this->state(fn () => ['type' => InvestmentOperationType::Sell]);
    }

    /**
     * @return Factory<InvestmentOperation>
     */
    public function dividend(): Factory
    {
        return $this->state(fn () => [
            'type' => InvestmentOperationType::Dividend,
            'quantity' => null,
            'unit_price' => null,
            'amount' => fake()->randomFloat(2, 5, 200),
        ]);
    }

    /**
     * @return Factory<InvestmentOperation>
     */
    public function interest(): Factory
    {
        return $this->state(fn () => [
            'type' => InvestmentOperationType::Interest,
            'quantity' => null,
            'unit_price' => null,
            'amount' => fake()->randomFloat(2, 5, 200),
        ]);
    }

    /**
     * @return Factory<InvestmentOperation>
     */
    public function deposit(): Factory
    {
        return $this->state(fn () => [
            'type' => InvestmentOperationType::Deposit,
            'quantity' => null,
            'unit_price' => null,
            'amount' => fake()->randomFloat(2, 100, 2000),
        ]);
    }

    /**
     * @return Factory<InvestmentOperation>
     */
    public function withdrawal(): Factory
    {
        return $this->state(fn () => [
            'type' => InvestmentOperationType::Withdrawal,
            'quantity' => null,
            'unit_price' => null,
            'amount' => fake()->randomFloat(2, 50, 500),
        ]);
    }
}
