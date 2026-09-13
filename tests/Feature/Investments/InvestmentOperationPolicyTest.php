<?php

use App\Models\InvestmentOperation;
use App\Models\User;

test('only the owner can view, update, or delete their investment operation', function (string $ability) {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $operation = InvestmentOperation::factory()->for($owner)->create();

    expect($owner->can($ability, $operation))->toBeTrue()
        ->and($otherUser->can($ability, $operation))->toBeFalse();
})->with(['view', 'update', 'delete']);
