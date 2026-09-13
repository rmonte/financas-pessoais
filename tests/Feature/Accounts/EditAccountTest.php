<?php

use App\Livewire\Accounts\Index;
use App\Models\Account;
use App\Models\Bank;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

test('the owner can edit their account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['name' => 'Conta Corrente', 'initial_balance' => 100]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('edit', $account->id)
        ->set('name', 'Conta Corrente Atualizada')
        ->set('initial_balance', '250')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('accounts', [
        'id' => $account->id,
        'name' => 'Conta Corrente Atualizada',
        'initial_balance' => 250,
    ]);
});

test('the owner can unset the bank by selecting the "none" option', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->for($user)->create();
    $account = Account::factory()->for($user)->create(['bank_id' => $bank->id]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('edit', $account->id)
        ->set('bank_id', '')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('accounts', [
        'id' => $account->id,
        'bank_id' => null,
    ]);
});

test('editing to a name already used by the same user fails validation', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Conta Corrente']);
    $account = Account::factory()->for($user)->create(['name' => 'Poupança']);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('edit', $account->id)
        ->set('name', 'Conta Corrente')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);

    expect($account->fresh()->name)->toBe('Poupança');
});

test('a user cannot edit another user\'s account', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->for($owner)->create();
    $this->actingAs($otherUser);
    $this->withoutExceptionHandling();

    expect(fn () => Livewire::test(Index::class)->call('edit', $account->id))
        ->toThrow(AuthorizationException::class);
});
