<?php

use App\Enums\Currency;
use App\Livewire\Accounts\Index;
use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        config('services.exchange_rate.url') => Http::response(['USDBRL' => ['bid' => '5.00']]),
    ]);
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('accounts.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the accounts page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('accounts.index'));
    $response->assertOk();
});

test('only shows the authenticated user\'s accounts', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Account::factory()->for($user)->create(['name' => 'Conta Corrente']);
    Account::factory()->for($otherUser)->create(['name' => 'Conta Poupança']);

    $this->actingAs($user);

    Livewire::test(Index::class)->assertSee('Conta Corrente')->assertDontSee('Conta Poupança');
});

test('the total balance only counts active accounts and converts USD to BRL', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['initial_balance' => 1000, 'is_active' => true]);
    Account::factory()->for($user)->create(['initial_balance' => 500, 'is_active' => false]);
    Account::factory()->for($user)->create(['currency' => Currency::USD, 'initial_balance' => 100, 'is_active' => true]);

    $this->actingAs($user);

    expect((float) Livewire::test(Index::class)->get('totalBalanceInBrl'))->toBe(1500.0)
        ->and(Livewire::test(Index::class)->get('activeAccountsCount'))->toBe(2);
});
