<?php

use App\Livewire\Investments\Index;
use App\Models\Investment;
use App\Models\InvestmentOperation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        config('services.exchange_rate.url') => Http::response(['USDBRL' => ['bid' => '5.20']]),
    ]);
});

test('the owner can delete their investment and its operations', function () {
    $user = User::factory()->create();
    $investment = Investment::factory()->for($user)->create();
    $operation = InvestmentOperation::factory()->for($user)->create(['investment_id' => $investment->id]);
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('confirmDelete', $investment->id)
        ->call('delete');

    $this->assertDatabaseMissing('investments', ['id' => $investment->id]);
    $this->assertDatabaseMissing('investment_operations', ['id' => $operation->id]);
});

test('a user cannot delete another user\'s investment', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $investment = Investment::factory()->for($owner)->create();
    $this->actingAs($otherUser);
    $this->withoutExceptionHandling();

    expect(fn () => Livewire::test(Index::class)->call('confirmDelete', $investment->id))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseHas('investments', ['id' => $investment->id]);
});
