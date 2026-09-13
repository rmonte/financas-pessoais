<?php

use App\Enums\InvestmentOperationType;
use App\Enums\InvestmentType;
use App\Livewire\Investments\Show;
use App\Models\Investment;
use App\Models\InvestmentOperation;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        config('services.exchange_rate.url') => Http::response(['USDBRL' => ['bid' => '5.20']]),
    ]);
});

test('a user cannot view another user\'s investment', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $investment = Investment::factory()->for($owner)->create();
    $this->actingAs($otherUser);

    $response = $this->get(route('investments.show', $investment));

    $response->assertForbidden();
});

test('lists the operations for the investment', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock]);
    InvestmentOperation::factory()->for($user)->create([
        'investment_id' => $investment->id,
        'description' => 'Compra inicial',
    ]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->assertSee('Compra inicial');
});

test('a buy operation can be created and the amount is calculated from quantity and unit price', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock]);
    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->set('type', InvestmentOperationType::Buy->value)
        ->set('quantity', '10')
        ->set('unit_price', '25.50')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('investment_operations', [
        'investment_id' => $investment->id,
        'user_id' => $user->id,
        'type' => InvestmentOperationType::Buy->value,
        'quantity' => 10,
        'unit_price' => 25.5,
        'amount' => 255,
    ]);
});

test('a deposit operation does not require quantity or unit price', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->fixedIncome()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->set('type', InvestmentOperationType::Deposit->value)
        ->set('amount', '500')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('investment_operations', [
        'investment_id' => $investment->id,
        'type' => InvestmentOperationType::Deposit->value,
        'quantity' => null,
        'unit_price' => null,
        'amount' => 500,
    ]);
});

test('a JCP (interest) operation can be recorded for a stock', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock]);
    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->set('type', InvestmentOperationType::Interest->value)
        ->set('amount', '45')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('investment_operations', [
        'investment_id' => $investment->id,
        'type' => InvestmentOperationType::Interest->value,
        'amount' => 45,
    ]);
});

test('an operation type not valid for the investment type is rejected', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->fixedIncome()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->set('type', InvestmentOperationType::Buy->value)
        ->set('quantity', '10')
        ->set('unit_price', '25')
        ->call('save')
        ->assertHasErrors(['type']);
});

test('quantity and unit price are required for a buy operation', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock]);
    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->set('type', InvestmentOperationType::Buy->value)
        ->set('quantity', '')
        ->set('unit_price', '')
        ->set('amount', '100')
        ->call('save')
        ->assertHasErrors(['quantity' => 'required', 'unit_price' => 'required']);
});

test('the owner can update an operation', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->fixedIncome()->for($user)->create();
    $operation = InvestmentOperation::factory()->deposit()->for($user)->create([
        'investment_id' => $investment->id,
        'amount' => 100,
    ]);
    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->call('edit', $operation->id)
        ->set('amount', '250')
        ->call('save')
        ->assertHasNoErrors();

    expect($operation->fresh()->amount)->toBe('250.00');
});

test('the owner can delete an operation', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->fixedIncome()->for($user)->create();
    $operation = InvestmentOperation::factory()->deposit()->for($user)->create(['investment_id' => $investment->id]);
    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->call('confirmDelete', $operation->id)
        ->call('delete');

    $this->assertDatabaseMissing('investment_operations', ['id' => $operation->id]);
});

test('a treasury bond accepts buy, sell, and interest but not dividend', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->treasury()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->set('type', InvestmentOperationType::Interest->value)
        ->set('amount', '30')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(Show::class, ['investment' => $investment])
        ->set('type', InvestmentOperationType::Dividend->value)
        ->set('amount', '30')
        ->call('save')
        ->assertHasErrors(['type']);
});

test('a pension plan only accepts buy and sell', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->pension()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Show::class, ['investment' => $investment])
        ->set('type', InvestmentOperationType::Buy->value)
        ->set('quantity', '10')
        ->set('unit_price', '12')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(Show::class, ['investment' => $investment])
        ->set('type', InvestmentOperationType::Deposit->value)
        ->set('amount', '100')
        ->call('save')
        ->assertHasErrors(['type']);
});
