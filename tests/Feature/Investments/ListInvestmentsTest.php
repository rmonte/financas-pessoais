<?php

use App\Enums\InvestmentType;
use App\Livewire\Investments\Index;
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

test('guests are redirected to the login page', function () {
    $response = $this->get(route('investments.index'));
    $response->assertRedirect(route('login'));
});

test('only shows the authenticated user\'s investments', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $investment = Investment::factory()->for($user)->create(['name' => 'Apple Inc.']);
    InvestmentOperation::factory()->for($user)->create(['investment_id' => $investment->id]);
    $otherInvestment = Investment::factory()->for($otherUser)->create(['name' => 'Outra pessoa']);
    InvestmentOperation::factory()->for($otherUser)->create(['investment_id' => $otherInvestment->id]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee('Apple Inc.')
        ->assertDontSee('Outra pessoa');
});

test('the active tab only shows investments with a positive quantity or balance', function () {
    $user = User::factory()->create();

    $active = Investment::factory()->for($user)->create(['name' => 'Ação ativa']);
    InvestmentOperation::factory()->for($user)->create(['investment_id' => $active->id]);

    $closed = Investment::factory()->for($user)->create(['name' => 'Ação zerada']);
    InvestmentOperation::factory()->for($user)->create(['investment_id' => $closed->id, 'quantity' => 10]);
    InvestmentOperation::factory()->sell()->for($user)->create(['investment_id' => $closed->id, 'quantity' => 10]);

    $this->actingAs($user);

    $test = Livewire::test(Index::class);

    expect($test->get('filteredInvestments')->pluck('name')->all())->toBe(['Ação ativa']);

    $test->set('statusFilter', 'inactive');

    expect($test->get('filteredInvestments')->pluck('name')->all())->toBe(['Ação zerada']);
});

test('groups the investment value by asset class', function () {
    $user = User::factory()->create();

    $stock = Investment::factory()->for($user)->create(['type' => InvestmentType::Stock, 'current_price' => 60]);
    InvestmentOperation::factory()->for($user)->create([
        'investment_id' => $stock->id,
        'quantity' => 10,
        'unit_price' => 50,
        'amount' => 500,
    ]);

    $treasury = Investment::factory()->treasury()->for($user)->create(['current_price' => 100]);
    InvestmentOperation::factory()->for($user)->create([
        'investment_id' => $treasury->id,
        'quantity' => 5,
        'unit_price' => 100,
        'amount' => 500,
    ]);

    $fixedIncome = Investment::factory()->fixedIncome()->for($user)->create();
    InvestmentOperation::factory()->deposit()->for($user)->create([
        'investment_id' => $fixedIncome->id,
        'amount' => 300,
    ]);

    $pension = Investment::factory()->pension()->for($user)->create(['current_price' => 10]);
    InvestmentOperation::factory()->for($user)->create([
        'investment_id' => $pension->id,
        'quantity' => 20,
        'unit_price' => 10,
        'amount' => 200,
    ]);

    $this->actingAs($user);

    $allocation = collect(Livewire::test(Index::class)->get('allocationByAssetClass'))->keyBy('id');

    expect($allocation['equity']['value'])->toBe(600.0)
        ->and($allocation['fixed_income']['value'])->toBe(800.0)
        ->and($allocation['pension']['value'])->toBe(200.0);
});
