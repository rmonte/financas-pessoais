<?php

use App\Livewire\Accounts\Index;
use App\Models\User;

test('a credit card account can be created with its billing fields', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Nubank Mastercard')
        ->set('initial_balance', '0')
        ->set('is_credit_card', true)
        ->set('credit_limit', '5000')
        ->set('closing_day', '10')
        ->set('due_day', '17')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'Nubank Mastercard',
        'is_credit_card' => true,
        'credit_limit' => 5000,
        'closing_day' => 10,
        'due_day' => 17,
    ]);
});

test('credit limit, closing day, and due day are required for credit cards', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Nubank Mastercard')
        ->set('is_credit_card', true)
        ->call('save')
        ->assertHasErrors(['credit_limit' => 'required', 'closing_day' => 'required', 'due_day' => 'required']);
});

test('closing day and due day must be between 1 and 31', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Nubank Mastercard')
        ->set('is_credit_card', true)
        ->set('credit_limit', '5000')
        ->set('closing_day', '35')
        ->set('due_day', '0')
        ->call('save')
        ->assertHasErrors(['closing_day' => 'between', 'due_day' => 'between']);
});

test('billing fields are cleared when the account is not a credit card', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Conta Corrente')
        ->set('initial_balance', '0')
        ->set('is_credit_card', false)
        ->set('credit_limit', '5000')
        ->set('closing_day', '10')
        ->set('due_day', '17')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'Conta Corrente',
        'is_credit_card' => false,
        'credit_limit' => null,
        'closing_day' => null,
        'due_day' => null,
    ]);
});
