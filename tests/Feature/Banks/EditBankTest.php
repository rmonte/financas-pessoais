<?php

use App\Livewire\Banks\Index;
use App\Models\Bank;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

test('the owner can edit their bank', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->for($user)->create(['name' => 'Nubank', 'code' => '260']);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('edit', $bank->id)
        ->set('name', 'Nubank Atualizado')
        ->set('code', '999')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('banks', [
        'id' => $bank->id,
        'name' => 'Nubank Atualizado',
        'code' => '999',
    ]);
});

test('editing to a name already used by the same user fails validation', function () {
    $user = User::factory()->create();
    Bank::factory()->for($user)->create(['name' => 'Nubank']);
    $bank = Bank::factory()->for($user)->create(['name' => 'Bradesco']);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('edit', $bank->id)
        ->set('name', 'Nubank')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);

    expect($bank->fresh()->name)->toBe('Bradesco');
});

test('keeping the same name while editing does not trigger a uniqueness error', function () {
    $user = User::factory()->create();
    $bank = Bank::factory()->for($user)->create(['name' => 'Nubank']);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('edit', $bank->id)
        ->set('code', '260')
        ->call('save')
        ->assertHasNoErrors();

    expect($bank->fresh()->code)->toBe('260');
});

test('a user cannot edit another user\'s bank', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $bank = Bank::factory()->for($owner)->create();
    $this->actingAs($otherUser);
    $this->withoutExceptionHandling();

    expect(fn () => Livewire::test(Index::class)->call('edit', $bank->id))
        ->toThrow(AuthorizationException::class);
});
