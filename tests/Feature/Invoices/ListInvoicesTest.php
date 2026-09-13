<?php

use App\Livewire\Invoices\Index;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('invoices.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the invoices page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('invoices.index'));
    $response->assertOk();
});

test('only shows the authenticated user\'s invoices', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $card = Account::factory()->for($user)->creditCard()->create(['name' => 'Meu Cartão']);
    $otherCard = Account::factory()->for($otherUser)->creditCard()->create(['name' => 'Cartão de outro']);

    Invoice::factory()->create(['user_id' => $user->id, 'account_id' => $card->id]);
    Invoice::factory()->create(['user_id' => $otherUser->id, 'account_id' => $otherCard->id]);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee('Meu Cartão')
        ->assertDontSee('Cartão de outro');
});

test('can filter invoices by card', function () {
    $user = User::factory()->create();
    $cardA = Account::factory()->for($user)->creditCard()->create(['name' => 'Cartão A']);
    $cardB = Account::factory()->for($user)->creditCard()->create(['name' => 'Cartão B']);

    $invoiceA = Invoice::factory()->create(['user_id' => $user->id, 'account_id' => $cardA->id]);
    Invoice::factory()->create(['user_id' => $user->id, 'account_id' => $cardB->id]);

    $this->actingAs($user);

    $invoices = Livewire::test(Index::class)
        ->set('filterAccountId', $cardA->id)
        ->get('invoices');

    expect($invoices)->toHaveCount(1)
        ->and($invoices->first()->id)->toBe($invoiceA->id);
});
