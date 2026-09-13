<?php

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;

test('the scheduled command generates due occurrences for every user', function () {
    $this->travelTo(Carbon::parse('2026-09-15'));

    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    RecurringTransaction::factory()->create(['user_id' => $user->id, 'start_date' => '2026-09-01']);
    RecurringTransaction::factory()->create(['user_id' => $otherUser->id, 'start_date' => '2026-09-01']);

    $this->artisan('app:generate-recurring-transactions')->assertSuccessful();

    expect(Transaction::count())->toBe(2);
});
