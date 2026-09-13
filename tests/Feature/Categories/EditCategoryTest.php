<?php

use App\Enums\CategoryType;
use App\Livewire\Categories\Index;
use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

test('the owner can edit their category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['name' => 'Alimentação', 'type' => CategoryType::Expense]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('edit', $category->id)
        ->set('name', 'Alimentação Atualizada')
        ->set('type', CategoryType::Income->value)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Alimentação Atualizada',
        'type' => CategoryType::Income->value,
    ]);
});

test('editing to a name already used by the same user fails validation', function () {
    $user = User::factory()->create();
    Category::factory()->for($user)->create(['name' => 'Alimentação']);
    $category = Category::factory()->for($user)->create(['name' => 'Transporte']);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('edit', $category->id)
        ->set('name', 'Alimentação')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);

    expect($category->fresh()->name)->toBe('Transporte');
});

test('a user cannot edit another user\'s category', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $category = Category::factory()->for($owner)->create();
    $this->actingAs($otherUser);
    $this->withoutExceptionHandling();

    expect(fn () => Livewire::test(Index::class)->call('edit', $category->id))
        ->toThrow(AuthorizationException::class);
});
