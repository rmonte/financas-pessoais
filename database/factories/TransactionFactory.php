<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
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
            'account_id' => fn (array $attributes) => Account::factory()->create(['user_id' => $attributes['user_id']])->id,
            'to_account_id' => null,
            'category_id' => fn (array $attributes) => Category::factory()->create([
                'user_id' => $attributes['user_id'],
                'type' => CategoryType::Expense,
            ])->id,
            'invoice_id' => null,
            'type' => TransactionType::Expense,
            'description' => fake()->optional()->sentence(),
            'date' => fake()->dateTimeBetween(now()->startOfMonth(), now())->format('Y-m-d'),
            'amount' => fake()->randomFloat(2, 1, 1000),
        ];
    }
}
