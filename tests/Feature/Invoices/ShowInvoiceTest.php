<?php

use App\Enums\TransactionType;
use App\Livewire\Invoices\Show;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;

test('the owner can view their invoice with its transactions', function () {
    $user = User::factory()->create();
    $card = Account::factory()->for($user)->creditCard()->create();
    $invoice = Invoice::factory()->create(['user_id' => $user->id, 'account_id' => $card->id]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $card->id,
        'invoice_id' => $invoice->id,
        'description' => 'Compra no cartão',
    ]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['invoice' => $invoice])
        ->assertSee('Compra no cartão');
});

test('a user cannot view another user\'s invoice', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $card = Account::factory()->for($owner)->creditCard()->create();
    $invoice = Invoice::factory()->create(['user_id' => $owner->id, 'account_id' => $card->id]);

    $this->actingAs($otherUser);

    $response = $this->get(route('invoices.show', $invoice));

    $response->assertForbidden();
});

test('marking an invoice as paid creates a transfer and links the payment', function () {
    $user = User::factory()->create();
    $card = Account::factory()->for($user)->creditCard()->create();
    $checking = Account::factory()->for($user)->create();
    $invoice = Invoice::factory()->create(['user_id' => $user->id, 'account_id' => $card->id]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'account_id' => $card->id,
        'invoice_id' => $invoice->id,
        'amount' => 250,
    ]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['invoice' => $invoice])
        ->set('paymentAccountId', $checking->id)
        ->call('pay')
        ->assertHasNoErrors();

    $invoice->refresh();

    expect($invoice->isPaid())->toBeTrue();

    $this->assertDatabaseHas('transactions', [
        'account_id' => $checking->id,
        'to_account_id' => $card->id,
        'type' => TransactionType::Transfer->value,
        'amount' => 250,
    ]);
});

test('the payment account must belong to the user and not be a credit card', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $card = Account::factory()->for($user)->creditCard()->create();
    $anotherCard = Account::factory()->for($user)->creditCard()->create();
    $othersAccount = Account::factory()->for($otherUser)->create();
    $invoice = Invoice::factory()->create(['user_id' => $user->id, 'account_id' => $card->id]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['invoice' => $invoice])
        ->set('paymentAccountId', $anotherCard->id)
        ->call('pay')
        ->assertHasErrors(['paymentAccountId']);

    Livewire::test(Show::class, ['invoice' => $invoice])
        ->set('paymentAccountId', $othersAccount->id)
        ->call('pay')
        ->assertHasErrors(['paymentAccountId']);
});
