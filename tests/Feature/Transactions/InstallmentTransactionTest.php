<?php

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Livewire\Transactions\Index;
use App\Models\Account;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;

test('an expense can be split into installments', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Expense->value)
        ->set('account_id', $account->id)
        ->set('category_id', $category->id)
        ->set('description', 'Notebook')
        ->set('date', '2026-09-10')
        ->set('amount', '1200')
        ->set('installments', 3)
        ->call('save')
        ->assertHasNoErrors();

    $transactions = Transaction::where('account_id', $account->id)->orderBy('installment_number')->get();

    expect($transactions)->toHaveCount(3);

    expect($transactions[0]->date->toDateString())->toBe('2026-09-10')
        ->and($transactions[1]->date->toDateString())->toBe('2026-10-10')
        ->and($transactions[2]->date->toDateString())->toBe('2026-11-10');

    expect($transactions[0]->description)->toBe('Notebook (1/3)')
        ->and($transactions[1]->description)->toBe('Notebook (2/3)')
        ->and($transactions[2]->description)->toBe('Notebook (3/3)');

    expect((float) $transactions[0]->amount + (float) $transactions[1]->amount + (float) $transactions[2]->amount)
        ->toBe(1200.0);

    expect($transactions[0]->installment_group_id)->not->toBeNull()
        ->and($transactions[0]->installment_group_id)->toBe($transactions[1]->installment_group_id)
        ->and($transactions[0]->installment_group_id)->toBe($transactions[2]->installment_group_id);
});

test('installment amounts handle rounding so the total matches exactly', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Expense->value)
        ->set('account_id', $account->id)
        ->set('category_id', $category->id)
        ->set('date', '2026-09-10')
        ->set('amount', '100')
        ->set('installments', 3)
        ->call('save');

    $transactions = Transaction::where('account_id', $account->id)->orderBy('installment_number')->pluck('amount');

    expect($transactions[0])->toBe('33.33')
        ->and($transactions[1])->toBe('33.33')
        ->and($transactions[2])->toBe('33.34');
});

test('each installment on a credit card is linked to its own monthly invoice', function () {
    $user = User::factory()->create();
    $card = Account::factory()->for($user)->creditCard()->create(['closing_day' => 10, 'due_day' => 17]);
    $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Expense->value)
        ->set('account_id', $card->id)
        ->set('category_id', $category->id)
        ->set('date', '2026-09-05')
        ->set('amount', '300')
        ->set('installments', 3)
        ->call('save');

    $transactions = Transaction::where('account_id', $card->id)->orderBy('installment_number')->get();

    expect(Invoice::where('account_id', $card->id)->count())->toBe(3);

    $invoiceMonths = $transactions->map(fn (Transaction $transaction) => $transaction->invoice->reference_month)->all();

    expect($invoiceMonths)->toBe([9, 10, 11]);
});

test('a single installment behaves like a normal transaction', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Expense->value)
        ->set('account_id', $account->id)
        ->set('category_id', $category->id)
        ->set('date', '2026-09-10')
        ->set('amount', '150')
        ->set('installments', 1)
        ->call('save')
        ->assertHasNoErrors();

    $transaction = Transaction::where('account_id', $account->id)->sole();

    expect($transaction->installment_number)->toBeNull()
        ->and($transaction->installment_total)->toBeNull()
        ->and($transaction->installment_group_id)->toBeNull();
});

test('installments are ignored for income transactions', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->for($user)->create(['type' => CategoryType::Income]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Income->value)
        ->set('account_id', $account->id)
        ->set('category_id', $category->id)
        ->set('date', '2026-09-10')
        ->set('amount', '150')
        ->set('installments', 5)
        ->call('save')
        ->assertHasNoErrors();

    expect(Transaction::where('account_id', $account->id)->count())->toBe(1);
});

test('editing an existing transaction never splits it into installments', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $transaction = Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'amount' => 100]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('edit', $transaction->id)
        ->set('installments', 6)
        ->set('amount', '120')
        ->call('save')
        ->assertHasNoErrors();

    expect(Transaction::where('account_id', $account->id)->count())->toBe(1);

    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'amount' => 120,
        'installment_total' => null,
    ]);
});
