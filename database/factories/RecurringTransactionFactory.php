<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringTransaction>
 */
class RecurringTransactionFactory extends Factory
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
            'type' => TransactionType::Expense,
            'description' => fake()->words(2, true),
            'amount' => fake()->randomFloat(2, 20, 300),
            'start_date' => now()->startOfMonth()->toDateString(),
            'is_active' => true,
        ];
    }
}
