<?php

use App\Livewire\RecurringTransactions\Index;
use App\Models\RecurringTransaction;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('recurring-transactions.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the recurring transactions page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('recurring-transactions.index'));
    $response->assertOk();
});

test('only shows the authenticated user\'s recurring transactions', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    RecurringTransaction::factory()->create(['user_id' => $user->id, 'description' => 'Aluguel']);
    RecurringTransaction::factory()->create(['user_id' => $otherUser->id, 'description' => 'Academia de outro']);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee('Aluguel')
        ->assertDontSee('Academia de outro');
});
