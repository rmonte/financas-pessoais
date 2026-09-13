<?php

namespace App\Models;

use App\Enums\TransactionType;
use Database\Factories\RecurringTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $account_id
 * @property int|null $to_account_id
 * @property int|null $category_id
 * @property TransactionType $type
 * @property string|null $description
 * @property string $amount
 * @property Carbon $start_date
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RecurringTransaction extends Model
{
    /** @use HasFactory<RecurringTransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id', 'to_account_id', 'category_id', 'type', 'description', 'amount', 'start_date', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Generate a given user's recurring transactions due up to the current month.
     */
    public static function generateDueForUser(User $user): void
    {
        $user->recurringTransactions()->where('is_active', true)->get()
            ->each(fn (self $recurringTransaction) => $recurringTransaction->generateDueOccurrences());
    }

    /**
     * Generate every active recurring transaction due up to the current month, across all
     * users. Intended for the scheduled command; per-request pages use generateDueForUser()
     * as a freshness safety net instead of scanning every user on every visit.
     */
    public static function generateAllDue(): void
    {
        static::where('is_active', true)->get()
            ->each(fn (self $recurringTransaction) => $recurringTransaction->generateDueOccurrences());
    }

    /**
     * Create the transactions for every month between the last generated occurrence
     * (or the start date, if none exist yet) and the current month.
     */
    public function generateDueOccurrences(): void
    {
        $lastDate = $this->transactions()->max('date');
        $nextDate = $lastDate !== null ? Carbon::parse($lastDate)->addMonthNoOverflow() : $this->start_date->copy();

        $endOfCurrentMonth = now()->endOfMonth();

        while ($nextDate->lte($endOfCurrentMonth)) {
            $occurrenceDate = $nextDate->copy()->day(min($this->start_date->day, $nextDate->daysInMonth));

            $invoiceId = null;

            if ($this->type === TransactionType::Expense && $this->account->is_credit_card) {
                $invoiceId = Invoice::forAccountAndDate($this->account, $occurrenceDate)->id;
            }

            $this->transactions()->create([
                'user_id' => $this->user_id,
                'account_id' => $this->account_id,
                'to_account_id' => $this->to_account_id,
                'category_id' => $this->category_id,
                'invoice_id' => $invoiceId,
                'type' => $this->type,
                'description' => $this->description,
                'date' => $occurrenceDate->toDateString(),
                'amount' => $this->amount,
            ]);

            $nextDate = $nextDate->addMonthNoOverflow();
        }
    }
}
