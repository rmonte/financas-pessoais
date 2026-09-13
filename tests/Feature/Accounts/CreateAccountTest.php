<?php

use App\Enums\Currency;
use App\Livewire\Accounts\Index;
use App\Models\Account;
use App\Models\Bank;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        config('services.exchange_rate.url') => Http::response(['USDBRL' => ['bid' => '5.20']]),
    ]);
});

test('an account can be created with a bank', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Conta Corrente')
        ->set('bank_id', $bank->id)
        ->set('description', 'Minha conta principal')
        ->set('initial_balance', '150.50')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'bank_id' => $bank->id,
        'name' => 'Conta Corrente',
        'description' => 'Minha conta principal',
        'initial_balance' => 150.50,
    ]);
});

test('an account can be created without a bank', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Carteira')
        ->set('initial_balance', '20')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'bank_id' => null,
        'name' => 'Carteira',
    ]);
});

test('selecting the "none" option in the bank dropdown stores a null bank_id', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Carteira')
        ->set('bank_id', '')
        ->set('initial_balance', '20')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'bank_id' => null,
        'name' => 'Carteira',
    ]);
});

test('the name is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('the name must be unique for the same user', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Conta Corrente']);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Conta Corrente')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);

    expect(Account::where('user_id', $user->id)->where('name', 'Conta Corrente')->count())->toBe(1);
});

test('the initial balance is required and must be numeric', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Conta Corrente')
        ->set('initial_balance', 'not-a-number')
        ->call('save')
        ->assertHasErrors(['initial_balance' => 'numeric']);
});

test('a user cannot assign another user\'s bank to their account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherUsersBank = Bank::factory()->for($otherUser)->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Conta Corrente')
        ->set('bank_id', $otherUsersBank->id)
        ->call('save')
        ->assertHasErrors(['bank_id']);
});

test('a bank can be created inline from the account form and gets selected', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(Index::class)
        ->call('startCreatingBank')
        ->set('newBankName', 'Avenue')
        ->call('saveBank')
        ->assertHasNoErrors()
        ->assertSet('creatingBank', false);

    $bank = Bank::where('user_id', $user->id)->where('name', 'Avenue')->firstOrFail();

    $component->assertSet('bank_id', $bank->id);
});

test('the inline bank name is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('startCreatingBank')
        ->set('newBankName', '')
        ->call('saveBank')
        ->assertHasErrors(['newBankName' => 'required']);
});

test('the inline bank name must be unique for the same user', function () {
    $user = User::factory()->create();
    Bank::factory()->for($user)->create(['name' => 'Nubank']);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('startCreatingBank')
        ->set('newBankName', 'Nubank')
        ->call('saveBank')
        ->assertHasErrors(['newBankName' => 'unique']);
});

test('an account can be created in a foreign currency', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Dólares na Avenue')
        ->set('currency', Currency::USD->value)
        ->set('initial_balance', '3000')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'Dólares na Avenue',
        'currency' => Currency::USD->value,
        'initial_balance' => 3000,
    ]);
});

test('an account defaults to BRL when no currency is chosen', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Conta Corrente')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'Conta Corrente',
        'currency' => Currency::BRL->value,
    ]);
});
