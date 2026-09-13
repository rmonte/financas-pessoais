<?php

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

test('balance equals the initial balance when there are no transactions', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_balance' => 500]);

    expect($account->currentBalance())->toBe('500');
});

test('income increases the balance and expense decreases it', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_balance' => 500]);
    $incomeCategory = Category::factory()->for($user)->create(['type' => CategoryType::Income]);
    $expenseCategory = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $incomeCategory->id,
        'type' => TransactionType::Income,
        'amount' => 1000,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $expenseCategory->id,
        'type' => TransactionType::Expense,
        'amount' => 200,
    ]);

    expect($account->currentBalance())->toBe('1300');
});

test('outgoing transfers decrease the balance and incoming transfers increase it', function () {
    $user = User::factory()->create();
    $checking = Account::factory()->for($user)->create(['initial_balance' => 1000]);
    $savings = Account::factory()->for($user)->create(['initial_balance' => 0]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $checking->id,
        'to_account_id' => $savings->id,
        'category_id' => null,
        'type' => TransactionType::Transfer,
        'amount' => 300,
    ]);

    expect($checking->currentBalance())->toBe('700')
        ->and($savings->currentBalance())->toBe('300');
});

test('a credit card balance reflects debt from purchases minus payments received', function () {
    $user = User::factory()->create();
    $card = Account::factory()->for($user)->creditCard()->create(['initial_balance' => 0]);
    $checking = Account::factory()->for($user)->create(['initial_balance' => 1000]);
    $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $card->id,
        'category_id' => $category->id,
        'type' => TransactionType::Expense,
        'amount' => 300,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $checking->id,
        'to_account_id' => $card->id,
        'category_id' => null,
        'type' => TransactionType::Transfer,
        'amount' => 100,
    ]);

    expect($card->currentBalance())->toBe('-200');
});
