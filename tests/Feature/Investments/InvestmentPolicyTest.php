<?php

use App\Models\Investment;
use App\Models\User;

test('only the owner can view, update, or delete their investment', function (string $ability) {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $investment = Investment::factory()->for($owner)->create();

    expect($owner->can($ability, $investment))->toBeTrue()
        ->and($otherUser->can($ability, $investment))->toBeFalse();
})->with(['view', 'update', 'delete']);
