<?php

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Livewire\Transactions\Index;
use App\Models\Account;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\User;

test('an expense on a credit card account is linked to the invoice of its billing cycle', function () {
    $user = User::factory()->create();
    $card = Account::factory()->for($user)->creditCard()->create(['closing_day' => 10, 'due_day' => 17]);
    $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Expense->value)
        ->set('account_id', $card->id)
        ->set('category_id', $category->id)
        ->set('date', '2026-09-05')
        ->set('amount', '150')
        ->call('save')
        ->assertHasNoErrors();

    $invoice = Invoice::where('account_id', $card->id)->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->reference_month)->toBe(9)
        ->and($invoice->reference_year)->toBe(2026);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $card->id,
        'invoice_id' => $invoice->id,
        'amount' => 150,
    ]);
});

test('two purchases in the same billing cycle share the same invoice', function () {
    $user = User::factory()->create();
    $card = Account::factory()->for($user)->creditCard()->create(['closing_day' => 10, 'due_day' => 17]);
    $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Expense->value)
        ->set('account_id', $card->id)
        ->set('category_id', $category->id)
        ->set('date', '2026-09-02')
        ->set('amount', '50')
        ->call('save');

    Livewire::test(Index::class)
        ->set('type', TransactionType::Expense->value)
        ->set('account_id', $card->id)
        ->set('category_id', $category->id)
        ->set('date', '2026-09-09')
        ->set('amount', '30')
        ->call('save');

    expect(Invoice::where('account_id', $card->id)->count())->toBe(1);
});

test('an expense on a regular account is not linked to an invoice', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('type', TransactionType::Expense->value)
        ->set('account_id', $account->id)
        ->set('category_id', $category->id)
        ->set('date', '2026-09-05')
        ->set('amount', '150')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'invoice_id' => null,
    ]);

    expect(Invoice::count())->toBe(0);
});
