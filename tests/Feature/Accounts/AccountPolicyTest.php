<?php

use App\Models\Account;
use App\Models\User;

test('only the owner can view, update, or delete their accounts', function (string $ability) {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->for($owner)->create();

    expect($owner->can($ability, $account))->toBeTrue()
        ->and($otherUser->can($ability, $account))->toBeFalse();
})->with(['view', 'update', 'delete']);
