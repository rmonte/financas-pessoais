<?php

use App\Models\Bank;
use App\Models\User;

test('only the owner can view, update, or delete their banks', function (string $ability) {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $bank = Bank::factory()->for($owner)->create();

    expect($owner->can($ability, $bank))->toBeTrue()
        ->and($otherUser->can($ability, $bank))->toBeFalse();
})->with(['view', 'update', 'delete']);
