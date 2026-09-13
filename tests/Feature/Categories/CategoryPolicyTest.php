<?php

use App\Models\Category;
use App\Models\User;

test('only the owner can view, update, or delete their categories', function (string $ability) {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $category = Category::factory()->for($owner)->create();

    expect($owner->can($ability, $category))->toBeTrue()
        ->and($otherUser->can($ability, $category))->toBeFalse();
})->with(['view', 'update', 'delete']);
