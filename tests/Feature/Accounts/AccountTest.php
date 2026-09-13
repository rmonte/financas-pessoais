<?php

use App\Models\Account;
use App\Models\Bank;
use App\Models\User;
use Illuminate\Database\QueryException;

test('belongs to the user that created it', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    expect($account->user->is($user))->toBeTrue();
});

test('belongs to a bank', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->for($user)->create();
    $account = Account::factory()->for($user)->for($bank)->create();

    expect($account->bank->is($bank))->toBeTrue();
});

test('can be created without a bank', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['bank_id' => null]);

    expect($account->bank)->toBeNull();
});

test('does not allow two accounts with the same name for the same user', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Conta Corrente']);

    expect(fn () => Account::factory()->for($user)->create(['name' => 'Conta Corrente']))
        ->toThrow(QueryException::class);
});

test('allows different users to have accounts with the same name', function () {
    $accountA = Account::factory()->create(['name' => 'Conta Corrente']);
    $accountB = Account::factory()->create(['name' => 'Conta Corrente']);

    expect($accountB->id)->not->toBe($accountA->id);
});
