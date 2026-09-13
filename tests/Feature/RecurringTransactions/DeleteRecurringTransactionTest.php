<?php

use App\Livewire\RecurringTransactions\Index;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

test('deleting a recurring transaction keeps its already generated transactions', function () {
    $user = User::factory()->create();
    $recurring = RecurringTransaction::factory()->create(['user_id' => $user->id, 'start_date' => now()->toDateString()]);
    $recurring->generateDueOccurrences();
    $generatedTransactionId = $recurring->transactions()->sole()->id;

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('confirmDelete', $recurring->id)
        ->call('delete');

    $this->assertDatabaseMissing('recurring_transactions', ['id' => $recurring->id]);

    $this->assertDatabaseHas('transactions', [
        'id' => $generatedTransactionId,
        'recurring_transaction_id' => null,
    ]);
});

test('a user cannot delete another user\'s recurring transaction', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $recurring = RecurringTransaction::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($otherUser);
    $this->withoutExceptionHandling();

    expect(fn () => Livewire::test(Index::class)->call('confirmDelete', $recurring->id))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseHas('recurring_transactions', ['id' => $recurring->id]);
});
