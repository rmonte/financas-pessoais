<?php

use App\Livewire\Banks\Index;
use App\Models\Bank;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('banks.index'));
    $response->assertRedirect(route('login'));
});

// authenticated users can visit the banks page
test('authenticated users can visit the banks page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('banks.index'));
    $response->assertOk();
});

// only shows the authenticated user\'s banks
test('only shows the authenticated user\'s banks', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Bank::factory()->for($user)->create(['name' => 'Nubank']);
    Bank::factory()->for($otherUser)->create(['name' => 'Bradesco']);

    $this->actingAs($user);

    Livewire::test(Index::class)->assertSee('Nubank')->assertDontSee('Bradesco');
});
