<?php

use App\Enums\InvoiceStatus;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;

test('forAccountAndDate creates a new invoice for the billing cycle of the given date', function () {
    $user = User::factory()->create();
    $card = Account::factory()->for($user)->creditCard()->create(['closing_day' => 10, 'due_day' => 17]);

    $invoice = Invoice::forAccountAndDate($card, Carbon::parse('2026-09-05'));

    expect($invoice->account_id)->toBe($card->id)
        ->and($invoice->reference_month)->toBe(9)
        ->and($invoice->reference_year)->toBe(2026)
        ->and($invoice->closing_date->toDateString())->toBe('2026-09-10')
        ->and($invoice->due_date->toDateString())->toBe('2026-09-17');
});

test('forAccountAndDate reuses the invoice for purchases in the same cycle', function () {
    $user = User::factory()->create();
    $card = Account::factory()->for($user)->creditCard()->create(['closing_day' => 10, 'due_day' => 17]);

    $first = Invoice::forAccountAndDate($card, Carbon::parse('2026-09-02'));
    $second = Invoice::forAccountAndDate($card, Carbon::parse('2026-09-09'));

    expect($second->id)->toBe($first->id)
        ->and(Invoice::where('account_id', $card->id)->count())->toBe(1);
});

test('forAccountAndDate creates separate invoices for different cycles', function () {
    $user = User::factory()->create();
    $card = Account::factory()->for($user)->creditCard()->create(['closing_day' => 10, 'due_day' => 17]);

    $september = Invoice::forAccountAndDate($card, Carbon::parse('2026-09-05'));
    $october = Invoice::forAccountAndDate($card, Carbon::parse('2026-09-15'));

    expect($october->id)->not->toBe($september->id)
        ->and($october->reference_month)->toBe(10);
});

test('status is open before the closing date', function () {
    $invoice = Invoice::factory()->create(['closing_date' => now()->addDays(5)]);

    expect($invoice->status())->toBe(InvoiceStatus::Open);
});

test('status is closed after the closing date without a payment', function () {
    $invoice = Invoice::factory()->create(['closing_date' => now()->subDays(2)]);

    expect($invoice->status())->toBe(InvoiceStatus::Closed);
});

test('status is paid once a payment transaction is linked', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $transaction = Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);

    $invoice = Invoice::factory()->create([
        'user_id' => $user->id,
        'closing_date' => now()->subDays(2),
        'payment_transaction_id' => $transaction->id,
    ]);

    expect($invoice->status())->toBe(InvoiceStatus::Paid);
});

test('total sums only the transactions linked to the invoice', function () {
    $user = User::factory()->create();
    $card = Account::factory()->for($user)->creditCard()->create(['closing_day' => 10, 'due_day' => 17]);
    $invoice = Invoice::factory()->create(['user_id' => $user->id, 'account_id' => $card->id]);
    $otherInvoice = Invoice::factory()->create(['user_id' => $user->id]);

    Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $card->id, 'invoice_id' => $invoice->id, 'amount' => 100]);
    Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $card->id, 'invoice_id' => $invoice->id, 'amount' => 50]);
    Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $card->id, 'invoice_id' => $otherInvoice->id, 'amount' => 999]);

    expect($invoice->total())->toBe('150');
});
