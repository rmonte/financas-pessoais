<?php

namespace App\Models;

use App\Enums\TransactionType;
use Carbon\CarbonInterface;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property int $account_id
 * @property int|null $to_account_id
 * @property int|null $category_id
 * @property int|null $invoice_id
 * @property TransactionType $type
 * @property string|null $description
 * @property Carbon $date
 * @property string $amount
 * @property int|null $installment_number
 * @property int|null $installment_total
 * @property string|null $installment_group_id
 * @property int|null $recurring_transaction_id
 * @property int|null $investment_operation_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'account_id', 'to_account_id', 'category_id', 'invoice_id', 'type', 'description', 'date', 'amount',
        'installment_number', 'installment_total', 'installment_group_id', 'investment_operation_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'date' => 'date',
            'amount' => 'decimal:2',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recurringTransaction(): BelongsTo
    {
        return $this->belongsTo(RecurringTransaction::class);
    }

    public function investmentOperation(): BelongsTo
    {
        return $this->belongsTo(InvestmentOperation::class);
    }

    /**
     * Which invoice an expense belongs to, if it was made on a credit card account.
     */
    public static function resolveInvoiceId(?Account $account, TransactionType $type, CarbonInterface $date): ?int
    {
        if ($type !== TransactionType::Expense || ! $account?->is_credit_card) {
            return null;
        }

        return Invoice::forAccountAndDate($account, $date)->id;
    }

    /**
     * Split an expense into equal monthly installments, linked by a shared group id.
     *
     * @param  array{account_id: int, category_id: int|null, description: string|null}  $attributes
     */
    public static function createInstallments(User $user, array $attributes, float $totalAmount, CarbonInterface $baseDate, int $count): void
    {
        $account = Account::find($attributes['account_id']);
        $baseAmount = round($totalAmount / $count, 2);
        $lastAmount = round($totalAmount - ($baseAmount * ($count - 1)), 2);
        $groupId = (string) Str::uuid();
        $description = $attributes['description'];

        for ($number = 1; $number <= $count; $number++) {
            $installmentDate = $baseDate->copy()->addMonthsNoOverflow($number - 1);
            $installmentAmount = $number === $count ? $lastAmount : $baseAmount;

            $user->transactions()->create([
                'account_id' => $attributes['account_id'],
                'category_id' => $attributes['category_id'],
                'invoice_id' => static::resolveInvoiceId($account, TransactionType::Expense, $installmentDate),
                'type' => TransactionType::Expense,
                'description' => $description ? "{$description} ({$number}/{$count})" : "({$number}/{$count})",
                'date' => $installmentDate->toDateString(),
                'amount' => $installmentAmount,
                'installment_number' => $number,
                'installment_total' => $count,
                'installment_group_id' => $groupId,
            ]);
        }
    }
}
