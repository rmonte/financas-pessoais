<?php

use App\Models\Account;
use App\Models\Invoice;
use App\Models\User;

test('only the owner can view or update their invoices', function (string $ability) {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $card = Account::factory()->for($owner)->creditCard()->create();
    $invoice = Invoice::factory()->create(['user_id' => $owner->id, 'account_id' => $card->id]);

    expect($owner->can($ability, $invoice))->toBeTrue()
        ->and($otherUser->can($ability, $invoice))->toBeFalse();
})->with(['view', 'update']);
