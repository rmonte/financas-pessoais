<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\TransactionType;
use Carbon\CarbonInterface;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $bank_id
 * @property string $name
 * @property Currency $currency
 * @property string|null $description
 * @property string $initial_balance
 * @property bool $is_active
 * @property bool $is_credit_card
 * @property string|null $credit_limit
 * @property int|null $closing_day
 * @property int|null $due_day
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    protected $fillable = [
        'bank_id', 'name', 'currency', 'description', 'initial_balance', 'is_active',
        'is_credit_card', 'credit_limit', 'closing_day', 'due_day',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'initial_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'is_credit_card' => 'boolean',
            'credit_limit' => 'decimal:2',
            'closing_day' => 'integer',
            'due_day' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transaction::class, 'to_account_id')->where('type', TransactionType::Transfer);
    }

    /**
     * The transactions to compute the balance from: the eager-loaded collection when the
     * caller preloaded it (e.g. a listing page avoiding N+1 across many accounts), or a
     * fresh query otherwise — always correct even right after a new transaction was created.
     *
     * @return Collection<int, Transaction>
     */
    private function transactionsForCalculation(): Collection
    {
        $transactions = $this->relationLoaded('transactions') ? $this->transactions : $this->transactions()->get();
        $transactions->loadMissing('investmentOperation');

        return $transactions;
    }

    /**
     * @return Collection<int, Transaction>
     */
    private function incomingTransfersForCalculation(): Collection
    {
        return $this->relationLoaded('incomingTransfers') ? $this->incomingTransfers : $this->incomingTransfers()->get();
    }

    /**
     * Compute the current balance: initial balance plus every transaction affecting this account.
     */
    public function currentBalance(): string
    {
        $transactions = $this->transactionsForCalculation();

        $income = (float) $transactions->where('type', TransactionType::Income)->sum('amount');
        $expense = (float) $transactions->where('type', TransactionType::Expense)->sum('amount');
        $transferOut = (float) $transactions->where('type', TransactionType::Transfer)->sum('amount');
        $transferIn = (float) $this->incomingTransfersForCalculation()->sum('amount');
        $investmentOut = (float) $transactions->where('type', TransactionType::Investment)
            ->filter(fn (Transaction $transaction) => $transaction->investmentOperation->type->isOutgoing())
            ->sum('amount');
        $investmentIn = (float) $transactions->where('type', TransactionType::Investment)
            ->filter(fn (Transaction $transaction) => ! $transaction->investmentOperation->type->isOutgoing())
            ->sum('amount');

        return (string) ((float) $this->initial_balance + $income - $expense - $transferOut + $transferIn - $investmentOut + $investmentIn);
    }

    /**
     * Determine the invoice billing cycle a purchase on the given date falls into.
     *
     * @return array{closing_date: CarbonInterface, due_date: CarbonInterface, reference_month: int, reference_year: int}
     */
    public function billingCycleFor(CarbonInterface $date): array
    {
        $closingDate = $date->copy()->day(min($this->closing_day, $date->daysInMonth));

        if ($date->day > $closingDate->day) {
            $closingDate = $closingDate->addMonthNoOverflow();
            $closingDate = $closingDate->day(min($this->closing_day, $closingDate->daysInMonth));
        }

        $dueDate = $closingDate->copy()->day(min($this->due_day, $closingDate->daysInMonth));

        if ($this->due_day <= $closingDate->day) {
            $dueDate = $dueDate->addMonthNoOverflow();
            $dueDate = $dueDate->day(min($this->due_day, $dueDate->daysInMonth));
        }

        return [
            'closing_date' => $closingDate,
            'due_date' => $dueDate,
            'reference_month' => $closingDate->month,
            'reference_year' => $closingDate->year,
        ];
    }
}
