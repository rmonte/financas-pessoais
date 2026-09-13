<?php

use App\Models\InvestmentOperation;
use App\Models\Transaction;
use App\Models\User;

test('only the owner can view, update, or delete their transactions', function (string $ability) {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $transaction = Transaction::factory()->create(['user_id' => $owner->id]);

    expect($owner->can($ability, $transaction))->toBeTrue()
        ->and($otherUser->can($ability, $transaction))->toBeFalse();
})->with(['view', 'update', 'delete']);

test('the owner cannot update or delete a transaction linked to an investment operation', function (string $ability) {
    $owner = User::factory()->create();
    $operation = InvestmentOperation::factory()->for($owner)->create();
    $transaction = Transaction::factory()->create(['user_id' => $owner->id, 'investment_operation_id' => $operation->id]);

    expect($owner->can($ability, $transaction))->toBeFalse();
})->with(['update', 'delete']);
