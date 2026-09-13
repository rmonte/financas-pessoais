<?php

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

test('belongs to the user that created it', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->create(['user_id' => $user->id]);

    expect($transaction->user->is($user))->toBeTrue();
});

test('belongs to an account and a category', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->for($user)->create();
    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
    ]);

    expect($transaction->account->is($account))->toBeTrue()
        ->and($transaction->category->is($category))->toBeTrue();
});

test('a transfer references a destination account and has no category', function () {
    $user = User::factory()->create();
    $origin = Account::factory()->for($user)->create();
    $destination = Account::factory()->for($user)->create();

    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $origin->id,
        'to_account_id' => $destination->id,
        'category_id' => null,
        'type' => TransactionType::Transfer,
    ]);

    expect($transaction->toAccount->is($destination))->toBeTrue()
        ->and($transaction->category)->toBeNull();
});

test('casts type to the TransactionType enum', function () {
    $transaction = Transaction::factory()->create(['type' => TransactionType::Income]);

    expect($transaction->type)->toBe(TransactionType::Income);
});
