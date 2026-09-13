<?php

use App\Enums\InvestmentOperationType;
use App\Enums\InvestmentType;
use App\Enums\TransactionType;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Investments\Show;
use App\Models\Account;
use App\Models\Investment;
use App\Models\InvestmentOperation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        config('services.exchange_rate.url') => Http::response(['USDBRL' => ['bid' => '5.20']]),
    ]);
});

test('buying a stock with an account debits that account as an investment', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_balance' => 5000]);
    $investment = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock]);
    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->set('type', InvestmentOperationType::Buy->value)
        ->set('quantity', '10')
        ->set('unit_price', '50')
        ->set('account_id', $account->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($account->fresh()->currentBalance())->toBe('4500');

    $this->assertDatabaseHas('transactions', [
        'account_id' => $account->id,
        'type' => TransactionType::Investment->value,
        'category_id' => null,
        'amount' => 500,
    ]);
});

test('a deposit into a savings box debits the chosen account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_balance' => 2000]);
    $investment = Investment::factory()->fixedIncome()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->set('type', InvestmentOperationType::Deposit->value)
        ->set('amount', '300')
        ->set('account_id', $account->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($account->fresh()->currentBalance())->toBe('1700');
});

test('selling a stock with an account credits that account as an investment', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_balance' => 1000]);
    $investment = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock]);

    InvestmentOperation::factory()->for($user)->create([
        'investment_id' => $investment->id,
        'quantity' => 10,
        'unit_price' => 50,
        'amount' => 500,
    ]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->set('type', InvestmentOperationType::Sell->value)
        ->set('quantity', '5')
        ->set('unit_price', '60')
        ->set('account_id', $account->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($account->fresh()->currentBalance())->toBe('1300');

    $this->assertDatabaseHas('transactions', [
        'account_id' => $account->id,
        'type' => TransactionType::Investment->value,
        'amount' => 300,
    ]);
});

test('an operation without an account does not create a transaction', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->fixedIncome()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->set('type', InvestmentOperationType::Deposit->value)
        ->set('amount', '300')
        ->call('save')
        ->assertHasNoErrors();

    expect(Transaction::count())->toBe(0);
});

test('adding an account to an existing operation creates the linked transaction', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_balance' => 1000]);
    $investment = Investment::factory()->fixedIncome()->for($user)->create();
    $operation = InvestmentOperation::factory()->deposit()->for($user)->create([
        'investment_id' => $investment->id,
        'amount' => 200,
    ]);
    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->call('edit', $operation->id)
        ->set('account_id', $account->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($account->fresh()->currentBalance())->toBe('800')
        ->and($operation->fresh()->transaction_id)->not->toBeNull();
});

test('removing the account from an operation deletes the linked transaction', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_balance' => 1000]);
    $investment = Investment::factory()->fixedIncome()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->set('type', InvestmentOperationType::Deposit->value)
        ->set('amount', '200')
        ->set('account_id', $account->id)
        ->call('save');

    $operation = InvestmentOperation::sole();
    expect($account->fresh()->currentBalance())->toBe('800');

    Livewire::test(Show::class, ['investment' => $investment])
        ->call('edit', $operation->id)
        ->set('account_id', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($account->fresh()->currentBalance())->toBe('1000')
        ->and($operation->fresh()->transaction_id)->toBeNull()
        ->and(Transaction::count())->toBe(0);
});

test('updating the amount of a linked operation updates the linked transaction', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_balance' => 1000]);
    $investment = Investment::factory()->fixedIncome()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->set('type', InvestmentOperationType::Deposit->value)
        ->set('amount', '200')
        ->set('account_id', $account->id)
        ->call('save');

    $operation = InvestmentOperation::sole();

    Livewire::test(Show::class, ['investment' => $investment])
        ->call('edit', $operation->id)
        ->set('amount', '500')
        ->call('save')
        ->assertHasNoErrors();

    expect($account->fresh()->currentBalance())->toBe('500')
        ->and(Transaction::count())->toBe(1);
});

test('deleting an operation also deletes its linked transaction', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_balance' => 1000]);
    $investment = Investment::factory()->fixedIncome()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->set('type', InvestmentOperationType::Deposit->value)
        ->set('amount', '200')
        ->set('account_id', $account->id)
        ->call('save');

    $operation = InvestmentOperation::sole();
    expect(Transaction::count())->toBe(1);

    Livewire::test(Show::class, ['investment' => $investment])
        ->call('confirmDelete', $operation->id)
        ->call('delete');

    expect(Transaction::count())->toBe(0)
        ->and($account->fresh()->currentBalance())->toBe('1000');
});

test('linked investment transactions are excluded from the dashboard month summary', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['initial_balance' => 5000]);
    $investment = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock]);
    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->set('type', InvestmentOperationType::Buy->value)
        ->set('quantity', '10')
        ->set('unit_price', '50')
        ->set('date', now()->toDateString())
        ->set('account_id', $account->id)
        ->call('save');

    $summary = Livewire::test(DashboardIndex::class)->get('monthSummary');

    expect($summary)->toBe([
        'income' => '0',
        'expense' => '0',
        'balance' => '0',
    ]);
});
