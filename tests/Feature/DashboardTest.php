<?php

use App\Enums\CategoryType;
use App\Enums\Currency;
use App\Enums\InvestmentType;
use App\Enums\TransactionType;
use App\Livewire\Dashboard\Index;
use App\Models\Account;
use App\Models\Category;
use App\Models\Investment;
use App\Models\InvestmentOperation;
use App\Models\Invoice;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('shows the current balance for each active account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['name' => 'Conta Corrente', 'initial_balance' => 1000]);
    $category = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'type' => TransactionType::Expense,
        'amount' => 300,
    ]);

    $this->actingAs($user);

    $balances = Livewire::test(Index::class)->get('accounts')->pluck('name', 'id');

    expect($balances)->toHaveCount(1);

    $freshAccount = $account->fresh();

    expect($freshAccount->currentBalance())->toBe('700');
});

test('only shows active accounts', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Ativa', 'is_active' => true]);
    Account::factory()->for($user)->create(['name' => 'Inativa', 'is_active' => false]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee('Ativa')
        ->assertDontSee('Inativa');
});

test('shows the income, expense, and result for the current month', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $incomeCategory = Category::factory()->for($user)->create(['type' => CategoryType::Income]);
    $expenseCategory = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $incomeCategory->id,
        'type' => TransactionType::Income,
        'amount' => 2000,
        'date' => now()->toDateString(),
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $expenseCategory->id,
        'type' => TransactionType::Expense,
        'amount' => 500,
        'date' => now()->toDateString(),
    ]);

    $this->actingAs($user);

    $summary = Livewire::test(Index::class)->get('monthSummary');

    expect($summary)->toBe([
        'income' => '2000',
        'expense' => '500',
        'balance' => '1500',
    ]);
});

test('computes the patrimony evolution for the last 6 years', function () {
    $this->travelTo(Carbon::parse('2026-09-15'));

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_balance' => 1000]);
    $incomeCategory = Category::factory()->for($user)->create(['type' => CategoryType::Income]);
    $expenseCategory = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $incomeCategory->id,
        'type' => TransactionType::Income,
        'amount' => 500,
        'date' => '2026-07-10',
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $expenseCategory->id,
        'type' => TransactionType::Expense,
        'amount' => 200,
        'date' => '2026-09-05',
    ]);

    $this->actingAs($user);

    $evolution = Livewire::test(Index::class)->get('patrimonyEvolution');

    expect($evolution)->toBe([
        ['year' => '2021', 'balance' => 1000.0],
        ['year' => '2022', 'balance' => 1000.0],
        ['year' => '2023', 'balance' => 1000.0],
        ['year' => '2024', 'balance' => 1000.0],
        ['year' => '2025', 'balance' => 1000.0],
        ['year' => '2026', 'balance' => 1300.0],
    ]);
});

test('a stock purchase is reflected only from the year it happened onward', function () {
    $this->travelTo(Carbon::parse('2026-09-15'));

    $user = User::factory()->create();
    Account::factory()->for($user)->create(['initial_balance' => 1000]);
    $investment = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock, 'current_price' => 60]);

    InvestmentOperation::factory()->for($user)->create([
        'investment_id' => $investment->id,
        'quantity' => 10,
        'unit_price' => 50,
        'amount' => 500,
        'date' => '2026-07-10',
    ]);

    $this->actingAs($user);

    $evolution = collect(Livewire::test(Index::class)->get('patrimonyEvolution'))->keyBy('year');

    expect($evolution['2025']['balance'])->toBe(1000.0)
        ->and($evolution['2026']['balance'])->toBe(1600.0);
});

test('a fixed income deposit is reflected only from the year it happened onward', function () {
    $this->travelTo(Carbon::parse('2026-09-15'));

    $user = User::factory()->create();
    Account::factory()->for($user)->create(['initial_balance' => 1000]);
    $investment = Investment::factory()->fixedIncome()->for($user)->create();

    InvestmentOperation::factory()->deposit()->for($user)->create([
        'investment_id' => $investment->id,
        'amount' => 300,
        'date' => '2026-08-01',
    ]);

    $this->actingAs($user);

    $evolution = collect(Livewire::test(Index::class)->get('patrimonyEvolution'))->keyBy('year');

    expect($evolution['2025']['balance'])->toBe(1000.0)
        ->and($evolution['2026']['balance'])->toBe(1300.0);
});

test('transfers between active accounts do not change the total patrimony evolution', function () {
    $this->travelTo(Carbon::parse('2026-09-15'));

    $user = User::factory()->create();
    $origin = Account::factory()->for($user)->create(['initial_balance' => 1000]);
    $destination = Account::factory()->for($user)->create(['initial_balance' => 0]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $origin->id,
        'to_account_id' => $destination->id,
        'category_id' => null,
        'type' => TransactionType::Transfer,
        'amount' => 300,
        'date' => '2026-09-05',
    ]);

    $this->actingAs($user);

    $evolution = Livewire::test(Index::class)->get('patrimonyEvolution');

    expect(collect($evolution)->pluck('balance')->unique()->all())->toBe([1000.0]);
});

test('buying an investment with a linked account does not change the total patrimony evolution', function () {
    Http::preventStrayRequests();
    Http::fake([
        config('services.exchange_rate.url') => Http::response(['USDBRL' => ['bid' => '5.20']]),
    ]);

    $this->travelTo(Carbon::parse('2026-09-15'));

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_balance' => 1000]);
    $investment = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock, 'current_price' => 50]);

    $operation = InvestmentOperation::factory()->for($user)->create([
        'investment_id' => $investment->id,
        'account_id' => $account->id,
        'quantity' => 10,
        'unit_price' => 50,
        'amount' => 500,
        'date' => '2026-09-05',
    ]);
    $operation->syncLinkedTransaction();

    $this->actingAs($user);

    $evolution = Livewire::test(Index::class)->get('patrimonyEvolution');

    expect(collect($evolution)->pluck('balance')->unique()->all())->toBe([1000.0]);
});

test('computes the income and expense totals for the last 6 months', function () {
    $this->travelTo(Carbon::parse('2026-09-15'));

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $incomeCategory = Category::factory()->for($user)->create(['type' => CategoryType::Income]);
    $expenseCategory = Category::factory()->for($user)->create(['type' => CategoryType::Expense]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $incomeCategory->id,
        'type' => TransactionType::Income,
        'amount' => 2000,
        'date' => '2026-08-10',
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $expenseCategory->id,
        'type' => TransactionType::Expense,
        'amount' => 500,
        'date' => '2026-09-05',
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'to_account_id' => Account::factory()->for($user)->create()->id,
        'category_id' => null,
        'type' => TransactionType::Transfer,
        'amount' => 9999,
        'date' => '2026-09-05',
    ]);

    $this->actingAs($user);

    $byMonth = Livewire::test(Index::class)->get('incomeExpenseByMonth');

    expect($byMonth)->toBe([
        ['month' => 'Abr/26', 'income' => 0.0, 'expense' => 0.0],
        ['month' => 'Mai/26', 'income' => 0.0, 'expense' => 0.0],
        ['month' => 'Jun/26', 'income' => 0.0, 'expense' => 0.0],
        ['month' => 'Jul/26', 'income' => 0.0, 'expense' => 0.0],
        ['month' => 'Ago/26', 'income' => 2000.0, 'expense' => 0.0],
        ['month' => 'Set/26', 'income' => 0.0, 'expense' => 500.0],
    ]);
});

test('groups the current month expenses by category', function () {
    $this->travelTo(Carbon::parse('2026-09-15'));

    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $food = Category::factory()->for($user)->create(['type' => CategoryType::Expense, 'name' => 'Alimentação']);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $food->id,
        'type' => TransactionType::Expense,
        'amount' => 150,
        'date' => '2026-09-05',
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => null,
        'type' => TransactionType::Expense,
        'amount' => 50,
        'date' => '2026-09-06',
    ]);

    // Expense from a previous month must not be counted.
    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $account->id,
        'category_id' => $food->id,
        'type' => TransactionType::Expense,
        'amount' => 999,
        'date' => '2026-08-05',
    ]);

    $this->actingAs($user);

    $byCategory = Livewire::test(Index::class)->get('expenseByCategory');

    expect($byCategory)->toHaveCount(2)
        ->and(collect($byCategory)->firstWhere('label', 'Alimentação')['value'])->toBe(150.0)
        ->and(collect($byCategory)->firstWhere('label', 'Sem categoria')['value'])->toBe(50.0);
});

test('lists the upcoming unpaid invoices ordered by due date', function () {
    $user = User::factory()->create();

    $later = Invoice::factory()->for($user)->create(['due_date' => now()->addDays(20)]);
    $sooner = Invoice::factory()->for($user)->create(['due_date' => now()->addDays(5)]);
    Invoice::factory()->for($user)->create(['due_date' => now()->addDays(1), 'payment_transaction_id' => Transaction::factory()->for($user)->create()->id]);

    $this->actingAs($user);

    $invoices = Livewire::test(Index::class)->get('upcomingInvoices');

    expect($invoices->pluck('id')->all())->toBe([$sooner->id, $later->id]);
});

test('shows the total amount of each upcoming invoice', function () {
    $user = User::factory()->create();
    $card = Account::factory()->for($user)->creditCard()->create();
    $invoice = Invoice::factory()->create(['user_id' => $user->id, 'account_id' => $card->id, 'due_date' => now()->addDays(5)]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $card->id,
        'invoice_id' => $invoice->id,
        'amount' => 342.50,
    ]);

    $this->actingAs($user);

    Livewire::test(Index::class)->assertSee('342,50');
});

test('lists the active recurring transactions ordered by their next occurrence', function () {
    $this->travelTo(Carbon::parse('2026-09-15'));

    $user = User::factory()->create();

    $dueSoon = RecurringTransaction::factory()->create(['user_id' => $user->id, 'start_date' => '2026-09-01']);
    $dueLater = RecurringTransaction::factory()->create(['user_id' => $user->id, 'start_date' => '2026-06-20']);
    $inactive = RecurringTransaction::factory()->create(['user_id' => $user->id, 'start_date' => '2026-09-01', 'is_active' => false]);

    $dueSoon->generateDueOccurrences();
    $dueLater->generateDueOccurrences();
    $inactive->generateDueOccurrences();

    $this->actingAs($user);

    $recurring = Livewire::test(Index::class)->get('upcomingRecurringTransactions');

    expect($recurring->pluck('id')->all())->toBe([$dueSoon->id, $dueLater->id]);
});

test('accounts and investments totals are broken out separately and sum to the total balance', function () {
    Http::preventStrayRequests();

    $user = User::factory()->create();
    Account::factory()->for($user)->create(['initial_balance' => 1000]);
    $investment = Investment::factory()->fixedIncome()->for($user)->create();

    InvestmentOperation::factory()->deposit()->for($user)->create([
        'investment_id' => $investment->id,
        'amount' => 300,
    ]);

    $this->actingAs($user);

    $component = Livewire::test(Index::class);

    expect($component->get('accountsTotalValue'))->toBe('1000')
        ->and($component->get('investmentsTotalValue'))->toBe('300')
        ->and($component->get('totalBalance'))->toBe('1300');
});

test('the total balance includes BRL investments without calling the exchange rate API', function () {
    Http::preventStrayRequests();

    $user = User::factory()->create();
    Account::factory()->for($user)->create(['initial_balance' => 1000]);
    $investment = Investment::factory()->fixedIncome()->for($user)->create();

    InvestmentOperation::factory()->deposit()->for($user)->create([
        'investment_id' => $investment->id,
        'amount' => 300,
    ]);

    $this->actingAs($user);

    $totalBalance = Livewire::test(Index::class)->get('totalBalance');

    expect($totalBalance)->toBe('1300');
});

test('the total balance converts USD investments using the current exchange rate', function () {
    Http::preventStrayRequests();
    Http::fake([
        config('services.exchange_rate.url') => Http::response(['USDBRL' => ['bid' => '5.00']]),
    ]);

    $user = User::factory()->create();
    Account::factory()->for($user)->create(['initial_balance' => 1000]);
    $investment = Investment::factory()->for($user)->create([
        'type' => InvestmentType::Stock,
        'currency' => Currency::USD,
        'current_price' => 100,
    ]);

    InvestmentOperation::factory()->for($user)->create([
        'investment_id' => $investment->id,
        'quantity' => 1,
        'unit_price' => 100,
        'amount' => 100,
    ]);

    $this->actingAs($user);

    $totalBalance = Livewire::test(Index::class)->get('totalBalance');

    expect($totalBalance)->toBe('1500');
});

test('the total balance converts USD accounts using the current exchange rate', function () {
    Http::preventStrayRequests();
    Http::fake([
        config('services.exchange_rate.url') => Http::response(['USDBRL' => ['bid' => '5.00']]),
    ]);

    $user = User::factory()->create();
    Account::factory()->for($user)->create(['initial_balance' => 1000]);
    Account::factory()->usd()->for($user)->create(['initial_balance' => 100]);

    $this->actingAs($user);

    $totalBalance = Livewire::test(Index::class)->get('totalBalance');

    expect($totalBalance)->toBe('1500');
});

test('the patrimony evolution converts USD accounts using the current exchange rate', function () {
    $this->travelTo(Carbon::parse('2026-09-15'));

    Http::preventStrayRequests();
    Http::fake([
        config('services.exchange_rate.url') => Http::response(['USDBRL' => ['bid' => '5.00']]),
    ]);

    $user = User::factory()->create();
    Account::factory()->for($user)->create(['initial_balance' => 1000]);
    Account::factory()->usd()->for($user)->create(['initial_balance' => 100]);

    $this->actingAs($user);

    $evolution = Livewire::test(Index::class)->get('patrimonyEvolution');

    expect(collect($evolution)->last()['balance'])->toBe(1500.0);
});

test('closed investments with no balance left are not included in the total balance', function () {
    Http::preventStrayRequests();

    $user = User::factory()->create();
    Account::factory()->for($user)->create(['initial_balance' => 1000]);
    Investment::factory()->fixedIncome()->for($user)->create();

    $this->actingAs($user);

    $totalBalance = Livewire::test(Index::class)->get('totalBalance');

    expect($totalBalance)->toBe('1000');
});
