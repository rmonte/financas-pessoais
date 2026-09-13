<?php

use App\Enums\InvestmentType;
use App\Livewire\Investments\Index;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        config('services.exchange_rate.url') => Http::response(['USDBRL' => ['bid' => '5.20']]),
    ]);
});

test('the owner can update their investment', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->for($user)->create(['name' => 'Old name']);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('edit', $investment->id)
        ->set('name', 'New name')
        ->call('save')
        ->assertHasNoErrors();

    expect($investment->fresh()->name)->toBe('New name');
});

test('switching to a non-stock type clears the ticker and current price', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->for($user)->create([
        'type' => InvestmentType::Stock,
        'ticker' => 'AAPL',
        'current_price' => 150,
    ]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('edit', $investment->id)
        ->set('type', InvestmentType::FixedIncome->value)
        ->set('name', $investment->name)
        ->call('save')
        ->assertHasNoErrors();

    $investment->refresh();

    expect($investment->ticker)->toBeNull()
        ->and($investment->current_price)->toBeNull();
});

test('a user cannot update another user\'s investment', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $investment = Investment::factory()->for($owner)->create();
    $this->actingAs($otherUser);
    $this->withoutExceptionHandling();

    expect(fn () => Livewire::test(Index::class)->call('edit', $investment->id))
        ->toThrow(AuthorizationException::class);
});
