<?php

use App\Livewire\Categories\Index;
use App\Models\Category;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('categories.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the categories page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('categories.index'));
    $response->assertOk();
});

test('only shows the authenticated user\'s categories', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Category::factory()->for($user)->create(['name' => 'Alimentação']);
    Category::factory()->for($otherUser)->create(['name' => 'Transporte']);

    $this->actingAs($user);

    Livewire::test(Index::class)->assertSee('Alimentação')->assertDontSee('Transporte');
});
