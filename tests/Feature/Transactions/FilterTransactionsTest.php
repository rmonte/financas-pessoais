<?php

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Livewire\Transactions\Index;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

test('filtering by type only shows matching transactions', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    makeTransaction($user, $account, TransactionType::Income, ['description' => 'Salário']);
    makeTransaction($user, $account, TransactionType::Expense, ['description' => 'Aluguel']);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('filterType', TransactionType::Income->value)
        ->assertSee('Salário')
        ->assertDontSee('Aluguel');
});

test('filtering by account shows transactions where it is the origin or destination', function () {
    $user = User::factory()->create();
    $accountA = Account::factory()->for($user)->create();
    $accountB = Account::factory()->for($user)->create();
    $accountC = Account::factory()->for($user)->create();

    makeTransaction($user, $accountA, TransactionType::Expense, ['description' => 'Na conta A']);
    makeTransaction($user, $accountC, TransactionType::Expense, ['description' => 'Na conta C']);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $accountC->id,
        'to_account_id' => $accountA->id,
        'category_id' => null,
        'type' => TransactionType::Transfer,
        'description' => 'Transferência C para A',
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('filterAccountId', $accountA->id)
        ->assertSee('Na conta A')
        ->assertSee('Transferência C para A')
        ->assertDontSee('Na conta C');
});

test('filtering by category only shows matching transactions', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $foodCategory = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $foodCategory->id,
        'type' => TransactionType::Expense,
        'description' => 'Restaurante',
    ]);
    makeTransaction($user, $account, TransactionType::Expense, ['description' => 'Outra categoria']);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('filterCategoryId', $foodCategory->id)
        ->assertSee('Restaurante')
        ->assertDontSee('Outra categoria');
});

test('only shows transactions within the selected year and month', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    makeTransaction($user, $account, TransactionType::Expense, ['date' => '2026-01-10', 'description' => 'Dentro do período']);
    makeTransaction($user, $account, TransactionType::Expense, ['date' => '2026-03-01', 'description' => 'Fora do período (outro mês)']);
    makeTransaction($user, $account, TransactionType::Expense, ['date' => '2025-01-10', 'description' => 'Fora do período (outro ano)']);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('filterYear', 2026)
        ->set('filterMonth', 1)
        ->assertSee('Dentro do período')
        ->assertDontSee('Fora do período (outro mês)')
        ->assertDontSee('Fora do período (outro ano)');
});

test('defaults to the current year and month', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    makeTransaction($user, $account, TransactionType::Expense, ['date' => now()->toDateString(), 'description' => 'Deste mês']);
    makeTransaction($user, $account, TransactionType::Expense, ['date' => now()->subMonthsNoOverflow(2)->toDateString(), 'description' => 'De outro mês']);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee('Deste mês')
        ->assertDontSee('De outro mês');
});

test('clearing filters resets the year and month to the current one', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    makeTransaction($user, $account, TransactionType::Expense, ['date' => now()->toDateString(), 'description' => 'Deste mês']);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('filterYear', 2020)
        ->set('filterMonth', 1)
        ->assertDontSee('Deste mês')
        ->call('resetFilters')
        ->assertSee('Deste mês');
});

test('totals reflect the currently filtered transactions', function () {
    $user = User::factory()->create();
    $accountA = Account::factory()->for($user)->create();
    $accountB = Account::factory()->for($user)->create();

    makeTransaction($user, $accountA, TransactionType::Income, ['amount' => 1000]);
    makeTransaction($user, $accountA, TransactionType::Expense, ['amount' => 300]);
    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $accountA->id,
        'to_account_id' => $accountB->id,
        'category_id' => null,
        'type' => TransactionType::Transfer,
        'amount' => 200,
    ]);

    $this->actingAs($user);

    $component = Livewire::test(Index::class);

    expect($component->get('totals'))->toBe([
        'income' => '1000',
        'expense' => '300',
        'transfer' => '200',
        'investment' => '0',
        'balance' => '700',
    ]);

    $component->set('filterType', TransactionType::Income->value);

    expect($component->get('totals'))->toBe([
        'income' => '1000',
        'expense' => '0',
        'transfer' => '0',
        'investment' => '0',
        'balance' => '1000',
    ]);
});

test('clearing filters shows all transactions again', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    makeTransaction($user, $account, TransactionType::Income, ['description' => 'Salário']);
    makeTransaction($user, $account, TransactionType::Expense, ['description' => 'Aluguel']);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('filterType', TransactionType::Income->value)
        ->assertDontSee('Aluguel')
        ->call('resetFilters')
        ->assertSee('Salário')
        ->assertSee('Aluguel');
});
