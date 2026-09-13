<?php

use App\Livewire\Banks\Index;
use App\Models\Bank;
use App\Models\User;

test('a bank can be created with a name and code', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Nubank')
        ->set('code', '260')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('banks', [
        'user_id' => $user->id,
        'name' => 'Nubank',
        'code' => '260',
    ]);
});

test('the code is optional', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Nubank')
        ->set('code', '')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('banks', [
        'user_id' => $user->id,
        'name' => 'Nubank',
    ]);
});

test('the name is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name' => 'required']);
});

test('the name cannot exceed 100 characters', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', str_repeat('a', 101))
        ->call('save')
        ->assertHasErrors(['name' => 'max']);
});

test('the code cannot exceed 20 characters', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Nubank')
        ->set('code', str_repeat('1', 21))
        ->call('save')
        ->assertHasErrors(['code' => 'max']);
});

test('the name must be unique for the same user', function () {
    $user = User::factory()->create();
    Bank::factory()->for($user)->create(['name' => 'Nubank']);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Nubank')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);

    expect(Bank::where('user_id', $user->id)->where('name', 'Nubank')->count())->toBe(1);
});

test('different users can create banks with the same name', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Bank::factory()->for($otherUser)->create(['name' => 'Nubank']);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Nubank')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('banks', ['user_id' => $user->id, 'name' => 'Nubank']);
});
