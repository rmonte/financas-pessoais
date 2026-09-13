<?php

use App\Livewire\Banks\Index;
use App\Models\Bank;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

test('the owner can delete their bank', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('confirmDelete', $bank->id)
        ->call('delete');

    $this->assertDatabaseMissing('banks', ['id' => $bank->id]);
});

test('a user cannot delete another user\'s bank', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $bank = Bank::factory()->for($owner)->create();
    $this->actingAs($otherUser);
    $this->withoutExceptionHandling();

    expect(fn () => Livewire::test(Index::class)->call('confirmDelete', $bank->id))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseHas('banks', ['id' => $bank->id]);
});
