<?php

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Livewire\RecurringTransactions\Index;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;

test('a recurring expense can be created and immediately generates its first occurrence', function () {
    $this->travelTo(Carbon::parse('2026-09-15'));

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Expense->value)
        ->set('account_id', $account->id)
        ->set('category_id', $category->id)
        ->set('description', 'Aluguel')
        ->set('amount', '1200')
        ->set('start_date', '2026-09-05')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('recurring_transactions', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'description' => 'Aluguel',
        'amount' => 1200,
    ]);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'description' => 'Aluguel',
        'amount' => 1200,
    ]);

    $transaction = Transaction::where('user_id', $user->id)->sole();

    expect($transaction->date->toDateString())->toBe('2026-09-05');
});

test('a recurring transfer can be created without a category', function () {
    $user = User::factory()->create();
    $origin = Account::factory()->for($user)->create();
    $destination = Account::factory()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Transfer->value)
        ->set('account_id', $origin->id)
        ->set('to_account_id', $destination->id)
        ->set('amount', '500')
        ->set('start_date', now()->toDateString())
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('recurring_transactions', [
        'user_id' => $user->id,
        'account_id' => $origin->id,
        'to_account_id' => $destination->id,
        'category_id' => null,
    ]);
});

test('the account is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Expense->value)
        ->set('amount', '100')
        ->set('start_date', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['account_id' => 'required']);
});

test('a category is required for income and expense', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Expense->value)
        ->set('account_id', $account->id)
        ->set('amount', '100')
        ->set('start_date', now()->toDateString())
        ->call('save')
        ->assertHasErrors(['category_id' => 'required']);
});
