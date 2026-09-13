<?php

use App\Livewire\RecurringTransactions\Index;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

test('the owner can edit their recurring transaction', function () {
    $user = User::factory()->create();
    $recurring = RecurringTransaction::factory()->create(['user_id' => $user->id, 'description' => 'Original', 'amount' => 100]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('edit', $recurring->id)
        ->set('description', 'Atualizado')
        ->set('amount', '150')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('recurring_transactions', [
        'id' => $recurring->id,
        'description' => 'Atualizado',
        'amount' => 150,
    ]);
});

test('deactivating a recurring transaction is persisted', function () {
    $user = User::factory()->create();
    $recurring = RecurringTransaction::factory()->create(['user_id' => $user->id, 'is_active' => true]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('edit', $recurring->id)
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('recurring_transactions', [
        'id' => $recurring->id,
        'is_active' => false,
    ]);
});

test('a user cannot edit another user\'s recurring transaction', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $recurring = RecurringTransaction::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($otherUser);
    $this->withoutExceptionHandling();

    expect(fn () => Livewire::test(Index::class)->call('edit', $recurring->id))
        ->toThrow(AuthorizationException::class);
});
