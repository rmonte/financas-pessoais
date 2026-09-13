<?php

use App\Livewire\Accounts\Index;
use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

test('the owner can delete their account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('confirmDelete', $account->id)
        ->call('delete');

    $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
});

test('a user cannot delete another user\'s account', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->for($owner)->create();
    $this->actingAs($otherUser);
    $this->withoutExceptionHandling();

    expect(fn () => Livewire::test(Index::class)->call('confirmDelete', $account->id))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseHas('accounts', ['id' => $account->id]);
});
