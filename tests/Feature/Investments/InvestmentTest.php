<?php

use App\Enums\AssetClass;
use App\Enums\InvestmentType;
use App\Models\Investment;
use App\Models\InvestmentOperation;
use App\Models\User;

test('a stock quantity reflects buys minus sells', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock]);

    InvestmentOperation::factory()->for($user)->create([
        'investment_id' => $investment->id,
        'quantity' => 10,
        'unit_price' => 100,
        'amount' => 1000,
    ]);

    InvestmentOperation::factory()->sell()->for($user)->create([
        'investment_id' => $investment->id,
        'quantity' => 3,
        'unit_price' => 120,
        'amount' => 360,
    ]);

    expect($investment->currentQuantity())->toBe('7')
        ->and($investment->investedAmount())->toBe('640');
});

test('a stock current value uses the configured current price', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock, 'current_price' => 150]);

    InvestmentOperation::factory()->for($user)->create([
        'investment_id' => $investment->id,
        'quantity' => 10,
        'unit_price' => 100,
        'amount' => 1000,
    ]);

    expect($investment->currentValue())->toBe('1500');
});

test('a stock without a configured current price falls back to the average purchase price', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock, 'current_price' => null]);

    InvestmentOperation::factory()->for($user)->create([
        'investment_id' => $investment->id,
        'quantity' => 10,
        'unit_price' => 100,
        'amount' => 1000,
    ]);

    expect($investment->averagePrice())->toBe('100')
        ->and($investment->currentValue())->toBe('1000');
});

test('dividends received sums dividend and interest operations', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock]);

    InvestmentOperation::factory()->dividend()->for($user)->create([
        'investment_id' => $investment->id,
        'amount' => 25,
    ]);

    InvestmentOperation::factory()->dividend()->for($user)->create([
        'investment_id' => $investment->id,
        'amount' => 15,
    ]);

    expect($investment->dividendsReceived())->toBe('40');
});

test('a savings box balance reflects deposits, interest, and withdrawals', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->fixedIncome()->for($user)->create();

    InvestmentOperation::factory()->deposit()->for($user)->create([
        'investment_id' => $investment->id,
        'amount' => 1000,
    ]);

    InvestmentOperation::factory()->interest()->for($user)->create([
        'investment_id' => $investment->id,
        'amount' => 50,
    ]);

    InvestmentOperation::factory()->withdrawal()->for($user)->create([
        'investment_id' => $investment->id,
        'amount' => 200,
    ]);

    expect($investment->currentBalance())->toBe('850')
        ->and($investment->currentValue())->toBe('850')
        ->and($investment->dividendsReceived())->toBe('50');
});

test('the price change percentage compares the current price to the average purchase price', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock, 'current_price' => 120]);

    InvestmentOperation::factory()->for($user)->create([
        'investment_id' => $investment->id,
        'quantity' => 10,
        'unit_price' => 100,
        'amount' => 1000,
    ]);

    expect($investment->priceChangePercentage())->toBe('20');
});

test('the price change percentage is null without a current price or without any shares held', function () {
    $user = User::factory()->create();
    $withoutPrice = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock, 'current_price' => null]);
    $withoutShares = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock, 'current_price' => 100]);

    InvestmentOperation::factory()->for($user)->create([
        'investment_id' => $withoutPrice->id,
        'quantity' => 10,
        'unit_price' => 100,
        'amount' => 1000,
    ]);

    expect($withoutPrice->priceChangePercentage())->toBeNull()
        ->and($withoutShares->priceChangePercentage())->toBeNull();
});

test('capital gain reflects both unrealized appreciation and realized gains from sells', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock, 'current_price' => 55]);

    InvestmentOperation::factory()->for($user)->create([
        'investment_id' => $investment->id,
        'quantity' => 10,
        'unit_price' => 50,
        'amount' => 500,
    ]);

    InvestmentOperation::factory()->sell()->for($user)->create([
        'investment_id' => $investment->id,
        'quantity' => 3,
        'unit_price' => 60,
        'amount' => 180,
    ]);

    expect($investment->capitalGain())->toBe('65');
});

test('total gain combines capital gain and dividends received', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock, 'current_price' => 120]);

    InvestmentOperation::factory()->for($user)->create([
        'investment_id' => $investment->id,
        'quantity' => 10,
        'unit_price' => 100,
        'amount' => 1000,
    ]);

    InvestmentOperation::factory()->dividend()->for($user)->create([
        'investment_id' => $investment->id,
        'amount' => 30,
    ]);

    expect($investment->capitalGain())->toBe('200')
        ->and($investment->totalGain())->toBe('230');
});

test('capital gain and price change are not computed for savings box investments', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->fixedIncome()->for($user)->create();

    InvestmentOperation::factory()->deposit()->for($user)->create([
        'investment_id' => $investment->id,
        'amount' => 1000,
    ]);

    InvestmentOperation::factory()->interest()->for($user)->create([
        'investment_id' => $investment->id,
        'amount' => 50,
    ]);

    expect($investment->capitalGain())->toBe('0')
        ->and($investment->priceChangePercentage())->toBeNull()
        ->and($investment->totalGain())->toBe('50');
});

test('a stock is active while shares remain and inactive once fully sold', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock]);

    InvestmentOperation::factory()->for($user)->create([
        'investment_id' => $investment->id,
        'quantity' => 10,
        'unit_price' => 50,
        'amount' => 500,
    ]);

    expect($investment->isActive())->toBeTrue();

    InvestmentOperation::factory()->sell()->for($user)->create([
        'investment_id' => $investment->id,
        'quantity' => 10,
        'unit_price' => 60,
        'amount' => 600,
    ]);

    expect($investment->isActive())->toBeFalse();
});

test('a savings box is active while it holds a balance and inactive once emptied', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->fixedIncome()->for($user)->create();

    expect($investment->isActive())->toBeFalse();

    InvestmentOperation::factory()->deposit()->for($user)->create([
        'investment_id' => $investment->id,
        'amount' => 500,
    ]);

    expect($investment->isActive())->toBeTrue();

    InvestmentOperation::factory()->withdrawal()->for($user)->create([
        'investment_id' => $investment->id,
        'amount' => 500,
    ]);

    expect($investment->isActive())->toBeFalse();
});

test('a treasury bond is valued as quantity times price like a stock', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->treasury()->for($user)->create(['current_price' => 120]);

    InvestmentOperation::factory()->for($user)->create([
        'investment_id' => $investment->id,
        'quantity' => 5,
        'unit_price' => 100,
        'amount' => 500,
    ]);

    expect($investment->currentValue())->toBe('600')
        ->and($investment->capitalGain())->toBe('100')
        ->and($investment->isActive())->toBeTrue();
});

test('a pension plan is valued as quota quantity times price like a stock', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->pension()->for($user)->create(['current_price' => 12]);

    InvestmentOperation::factory()->for($user)->create([
        'investment_id' => $investment->id,
        'quantity' => 100,
        'unit_price' => 10,
        'amount' => 1000,
    ]);

    expect($investment->currentValue())->toBe('1200')
        ->and($investment->capitalGain())->toBe('200');
});

test('each investment type maps to the correct asset class', function () {
    expect(InvestmentType::Stock->assetClass())->toBe(AssetClass::Equity)
        ->and(InvestmentType::Treasury->assetClass())->toBe(AssetClass::FixedIncome)
        ->and(InvestmentType::FixedIncome->assetClass())->toBe(AssetClass::FixedIncome)
        ->and(InvestmentType::Pension->assetClass())->toBe(AssetClass::Pension);
});
