<?php

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\QueryException;

test('belongs to the user that created it', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    expect($category->user->is($user))->toBeTrue();
});

test('casts type to the CategoryType enum', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['type' => CategoryType::Income]);

    expect($category->type)->toBe(CategoryType::Income);
});

test('does not allow two categories with the same name for the same user', function () {
    $user = User::factory()->create();
    Category::factory()->for($user)->create(['name' => 'Alimentação']);

    expect(fn () => Category::factory()->for($user)->create(['name' => 'Alimentação']))
        ->toThrow(QueryException::class);
});

test('allows different users to have categories with the same name', function () {
    $categoryA = Category::factory()->create(['name' => 'Alimentação']);
    $categoryB = Category::factory()->create(['name' => 'Alimentação']);

    expect($categoryB->id)->not->toBe($categoryA->id);
});
