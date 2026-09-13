<?php

use App\Enums\TransactionType;
use App\Livewire\Transactions\Index;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

test('the owner can edit their transaction', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'description' => 'Original',
        'amount' => 100,
    ]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('edit', $transaction->id)
        ->set('description', 'Atualizada')
        ->set('amount', '200')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'description' => 'Atualizada',
        'amount' => 200,
    ]);
});

test('switching from expense to transfer clears the category and requires a destination account', function () {
    $user = User::factory()->create();
    $destination = Account::factory()->for($user)->create();
    $transaction = Transaction::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('edit', $transaction->id)
        ->set('type', TransactionType::Transfer->value)
        ->set('to_account_id', $destination->id)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'type' => TransactionType::Transfer->value,
        'to_account_id' => $destination->id,
        'category_id' => null,
    ]);
});

test('a user cannot edit another user\'s transaction', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $transaction = Transaction::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($otherUser);
    $this->withoutExceptionHandling();

    expect(fn () => Livewire::test(Index::class)->call('edit', $transaction->id))
        ->toThrow(AuthorizationException::class);
});
