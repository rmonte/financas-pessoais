<?php

use App\Enums\CategoryType;
use App\Livewire\Categories\Index;
use App\Models\Category;
use App\Models\User;

test('a category can be created', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Salário')
        ->set('type', CategoryType::Income->value)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'name' => 'Salário',
        'type' => CategoryType::Income->value,
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

test('the type must be a valid enum value', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Salário')
        ->set('type', 'invalid-type')
        ->call('save')
        ->assertHasErrors(['type']);
});

test('the name must be unique for the same user', function () {
    $user = User::factory()->create();
    Category::factory()->for($user)->create(['name' => 'Alimentação']);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Alimentação')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);

    expect(Category::where('user_id', $user->id)->where('name', 'Alimentação')->count())->toBe(1);
});
