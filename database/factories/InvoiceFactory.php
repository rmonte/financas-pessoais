<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $closingDate = Carbon::now()->startOfMonth()->day(10);

        return [
            'user_id' => User::factory(),
            'account_id' => fn (array $attributes) => Account::factory()->create([
                'user_id' => $attributes['user_id'],
                'is_credit_card' => true,
                'closing_day' => 10,
                'due_day' => 17,
            ])->id,
            'reference_month' => $closingDate->month,
            'reference_year' => $closingDate->year,
            'closing_date' => $closingDate->toDateString(),
            'due_date' => $closingDate->copy()->addDays(7)->toDateString(),
            'payment_transaction_id' => null,
        ];
    }
}
