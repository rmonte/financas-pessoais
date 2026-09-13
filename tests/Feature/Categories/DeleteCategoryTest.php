<?php

use App\Livewire\Categories\Index;
use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

test('the owner can delete their category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('confirmDelete', $category->id)
        ->call('delete');

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('a user cannot delete another user\'s category', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $category = Category::factory()->for($owner)->create();
    $this->actingAs($otherUser);
    $this->withoutExceptionHandling();

    expect(fn () => Livewire::test(Index::class)->call('confirmDelete', $category->id))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});
