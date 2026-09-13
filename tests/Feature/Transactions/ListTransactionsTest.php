<?php

use App\Livewire\Transactions\Index;
use App\Models\Transaction;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('transactions.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the transactions page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('transactions.index'));
    $response->assertOk();
});

test('only shows the authenticated user\'s transactions', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Transaction::factory()->create(['user_id' => $user->id, 'description' => 'Compra do usuário']);
    Transaction::factory()->create(['user_id' => $otherUser->id, 'description' => 'Compra de outro usuário']);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee('Compra do usuário')
        ->assertDontSee('Compra de outro usuário');
});
