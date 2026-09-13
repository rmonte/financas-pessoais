<?php

use App\Livewire\Transactions\Index;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

test('the owner can delete their transaction', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('confirmDelete', $transaction->id)
        ->call('delete');

    $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
});

test('a user cannot delete another user\'s transaction', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $transaction = Transaction::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($otherUser);
    $this->withoutExceptionHandling();

    expect(fn () => Livewire::test(Index::class)->call('confirmDelete', $transaction->id))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
});
