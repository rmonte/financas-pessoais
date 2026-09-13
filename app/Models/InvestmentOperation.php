<?php

namespace App\Models;

use App\Enums\InvestmentOperationType;
use App\Enums\TransactionType;
use Database\Factories\InvestmentOperationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $investment_id
 * @property int|null $account_id
 * @property int|null $transaction_id
 * @property InvestmentOperationType $type
 * @property Carbon $date
 * @property string|null $quantity
 * @property string|null $unit_price
 * @property string $amount
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class InvestmentOperation extends Model
{
    /** @use HasFactory<InvestmentOperationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'investment_id', 'account_id', 'transaction_id', 'type', 'date', 'quantity', 'unit_price', 'amount', 'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InvestmentOperationType::class,
            'date' => 'date',
            'quantity' => 'decimal:6',
            'unit_price' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Create, update, or remove the bank transaction linked to this operation, based on
     * whether an account was chosen (money crossing the account/investment boundary).
     */
    public function syncLinkedTransaction(): void
    {
        if ($this->account_id === null) {
            $this->transaction?->delete();

            if ($this->transaction_id !== null) {
                $this->update(['transaction_id' => null]);
            }

            return;
        }

        $attributes = [
            'account_id' => $this->account_id,
            'category_id' => null,
            'type' => TransactionType::Investment,
            'description' => __(':operation: :investment', ['operation' => $this->type->label(), 'investment' => $this->investment->name]),
            'date' => $this->date->toDateString(),
            'amount' => $this->amount,
        ];

        if ($this->transaction !== null) {
            $this->transaction->update($attributes);
        } else {
            $transaction = $this->user->transactions()->create([...$attributes, 'investment_operation_id' => $this->id]);
            $this->update(['transaction_id' => $transaction->id]);
        }
    }
}
