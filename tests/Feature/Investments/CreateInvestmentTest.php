<?php

use App\Enums\Currency;
use App\Enums\InvestmentOperationType;
use App\Enums\InvestmentType;
use App\Livewire\Investments\Index;
use App\Models\Bank;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        config('services.exchange_rate.url') => Http::response(['USDBRL' => ['bid' => '5.20']]),
    ]);
});

test('a stock investment can be created together with its first operation', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('operationInvestmentId', '__new__')
        ->set('type', InvestmentType::Stock->value)
        ->set('name', 'Apple Inc.')
        ->set('bank_id', $bank->id)
        ->set('ticker', 'AAPL')
        ->set('currency', Currency::USD->value)
        ->set('operationType', InvestmentOperationType::Buy->value)
        ->set('operationDate', now()->toDateString())
        ->set('operationQuantity', '10')
        ->set('operationUnitPrice', '150.50')
        ->call('saveOperation')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('investments', [
        'user_id' => $user->id,
        'bank_id' => $bank->id,
        'type' => InvestmentType::Stock->value,
        'name' => 'Apple Inc.',
        'ticker' => 'AAPL',
        'currency' => Currency::USD->value,
        'current_price' => 150.5,
    ]);

    $this->assertDatabaseHas('investment_operations', [
        'type' => InvestmentOperationType::Buy->value,
        'quantity' => 10,
        'unit_price' => 150.5,
    ]);
});

test('a fixed income investment does not require a ticker', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('operationInvestmentId', '__new__')
        ->set('type', InvestmentType::FixedIncome->value)
        ->set('name', 'Caixinha Reserva de Emergência')
        ->set('operationType', InvestmentOperationType::Deposit->value)
        ->set('operationDate', now()->toDateString())
        ->set('operationAmount', '300')
        ->call('saveOperation')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('investments', [
        'user_id' => $user->id,
        'type' => InvestmentType::FixedIncome->value,
        'name' => 'Caixinha Reserva de Emergência',
        'ticker' => null,
        'current_price' => null,
    ]);
});

test('the ticker is required for stocks', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('operationInvestmentId', '__new__')
        ->set('type', InvestmentType::Stock->value)
        ->set('name', 'Apple Inc.')
        ->set('ticker', '')
        ->set('operationType', InvestmentOperationType::Buy->value)
        ->set('operationQuantity', '10')
        ->set('operationUnitPrice', '150.50')
        ->call('saveOperation')
        ->assertHasErrors(['ticker' => 'required']);
});

test('the name is required for a new investment', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('operationInvestmentId', '__new__')
        ->set('name', '')
        ->set('ticker', 'AAPL')
        ->set('operationType', InvestmentOperationType::Buy->value)
        ->set('operationQuantity', '10')
        ->set('operationUnitPrice', '150.50')
        ->call('saveOperation')
        ->assertHasErrors(['name' => 'required']);
});

test('an operation cannot be saved without selecting or creating an investment', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('operationInvestmentId', '')
        ->call('saveOperation')
        ->assertHasErrors(['operationInvestmentId']);
});
