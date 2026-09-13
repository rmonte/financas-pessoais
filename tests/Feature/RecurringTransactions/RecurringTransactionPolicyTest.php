<?php

use App\Models\RecurringTransaction;
use App\Models\User;

test('only the owner can view, update, or delete their recurring transactions', function (string $ability) {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $recurring = RecurringTransaction::factory()->create(['user_id' => $owner->id]);

    expect($owner->can($ability, $recurring))->toBeTrue()
        ->and($otherUser->can($ability, $recurring))->toBeFalse();
})->with(['view', 'update', 'delete']);
