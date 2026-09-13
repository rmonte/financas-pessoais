<?php

use App\Models\Bank;
use App\Models\User;
use Illuminate\Database\QueryException;

test('belongs to the user that created it', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->for($user)->create();

    expect($bank->user->is($user))->toBeTrue();
});

test('does not allow two banks with the same name for the same user', function () {
    $user = User::factory()->create();
    Bank::factory()->for($user)->create(['name' => 'Nubank']);

    expect(fn () => Bank::factory()->for($user)->create(['name' => 'Nubank']))->toThrow(QueryException::class);
});

test('allows different users to have banks with the same name', function () {
    $bankA = Bank::factory()->create(['name' => 'Nubank']);
    $bankB = Bank::factory()->create(['name' => 'Nubank']);

    expect($bankB->id)->not->toBe($bankA->id);
});
