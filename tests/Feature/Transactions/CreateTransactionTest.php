<?php

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Livewire\Transactions\Index;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;

test('an expense transaction can be created', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Expense->value)
        ->set('account_id', $account->id)
        ->set('category_id', $category->id)
        ->set('description', 'Supermercado')
        ->set('date', '2026-09-01')
        ->set('amount', '150.75')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'to_account_id' => null,
        'type' => TransactionType::Expense->value,
        'description' => 'Supermercado',
        'amount' => 150.75,
    ]);
});

test('an income transaction can be created', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->for($user)->create(['type' => CategoryType::Income]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Income->value)
        ->set('account_id', $account->id)
        ->set('category_id', $category->id)
        ->set('date', '2026-09-01')
        ->set('amount', '3000')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'type' => TransactionType::Income->value,
        'amount' => 3000,
    ]);
});

test('a transfer transaction can be created without a category', function () {
    $user = User::factory()->create();
    $origin = Account::factory()->for($user)->create();
    $destination = Account::factory()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Transfer->value)
        ->set('account_id', $origin->id)
        ->set('to_account_id', $destination->id)
        ->set('date', '2026-09-01')
        ->set('amount', '500')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $origin->id,
        'to_account_id' => $destination->id,
        'category_id' => null,
        'type' => TransactionType::Transfer->value,
        'amount' => 500,
    ]);
});

test('the account is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Expense->value)
        ->set('date', '2026-09-01')
        ->set('amount', '10')
        ->call('save')
        ->assertHasErrors(['account_id' => 'required']);
});

test('the amount must be greater than zero', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Expense->value)
        ->set('account_id', $account->id)
        ->set('category_id', $category->id)
        ->set('date', '2026-09-01')
        ->set('amount', '0')
        ->call('save')
        ->assertHasErrors(['amount' => 'gt']);
});

test('a category is required for income and expense transactions', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Expense->value)
        ->set('account_id', $account->id)
        ->set('date', '2026-09-01')
        ->set('amount', '10')
        ->call('save')
        ->assertHasErrors(['category_id' => 'required']);
});

test('the category type must match the transaction type', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $incomeCategory = Category::factory()->for($user)->create(['type' => CategoryType::Income]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Expense->value)
        ->set('account_id', $account->id)
        ->set('category_id', $incomeCategory->id)
        ->set('date', '2026-09-01')
        ->set('amount', '10')
        ->call('save')
        ->assertHasErrors(['category_id']);
});

test('a destination account is required for transfers', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Transfer->value)
        ->set('account_id', $account->id)
        ->set('date', '2026-09-01')
        ->set('amount', '10')
        ->call('save')
        ->assertHasErrors(['to_account_id' => 'required']);
});

test('the destination account must differ from the origin account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Transfer->value)
        ->set('account_id', $account->id)
        ->set('to_account_id', $account->id)
        ->set('date', '2026-09-01')
        ->set('amount', '10')
        ->call('save')
        ->assertHasErrors(['to_account_id']);
});

test('a user cannot use another user\'s account or category', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $othersAccount = Account::factory()->for($otherUser)->create();
    $othersCategory = Category::factory()->for($otherUser)->create(['type' => CategoryType::Expense]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Expense->value)
        ->set('account_id', $othersAccount->id)
        ->set('category_id', $othersCategory->id)
        ->set('date', '2026-09-01')
        ->set('amount', '10')
        ->call('save')
        ->assertHasErrors(['account_id', 'category_id']);
});

test('a transaction cannot be manually created with the investment type', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Investment->value)
        ->set('account_id', $account->id)
        ->set('date', '2026-09-01')
        ->set('amount', '10')
        ->call('save')
        ->assertHasErrors(['type']);
});
