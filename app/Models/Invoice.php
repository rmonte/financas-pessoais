<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Carbon\CarbonInterface;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $account_id
 * @property int $reference_month
 * @property int $reference_year
 * @property Carbon $closing_date
 * @property Carbon $due_date
 * @property int|null $payment_transaction_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'account_id', 'reference_month', 'reference_year', 'closing_date', 'due_date', 'payment_transaction_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closing_date' => 'date',
            'due_date' => 'date',
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

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'payment_transaction_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Find or create the invoice covering the billing cycle a purchase on the given date falls into.
     */
    public static function forAccountAndDate(Account $account, CarbonInterface $date): self
    {
        $cycle = $account->billingCycleFor($date);

        return static::firstOrCreate(
            [
                'account_id' => $account->id,
                'reference_month' => $cycle['reference_month'],
                'reference_year' => $cycle['reference_year'],
            ],
            [
                'user_id' => $account->user_id,
                'closing_date' => $cycle['closing_date'],
                'due_date' => $cycle['due_date'],
            ]
        );
    }

    public function isPaid(): bool
    {
        return $this->payment_transaction_id !== null;
    }

    public function status(): InvoiceStatus
    {
        if ($this->isPaid()) {
            return InvoiceStatus::Paid;
        }

        return now()->startOfDay()->gte($this->closing_date) ? InvoiceStatus::Closed : InvoiceStatus::Open;
    }

    public function total(): string
    {
        $transactions = $this->relationLoaded('transactions') ? $this->transactions : $this->transactions()->get();

        return (string) $transactions->sum('amount');
    }
}
