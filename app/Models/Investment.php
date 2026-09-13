<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\InvestmentOperationType;
use App\Enums\InvestmentType;
use Database\Factories\InvestmentFactory;
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
 * @property InvestmentType $type
 * @property string $name
 * @property string|null $ticker
 * @property Currency $currency
 * @property string|null $current_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Investment extends Model
{
    /** @use HasFactory<InvestmentFactory> */
    use HasFactory;

    protected $fillable = [
        'bank_id', 'type', 'name', 'ticker', 'currency', 'current_price',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InvestmentType::class,
            'currency' => Currency::class,
            'current_price' => 'decimal:4',
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
     * @return HasMany<InvestmentOperation, $this>
     */
    public function operations(): HasMany
    {
        return $this->hasMany(InvestmentOperation::class);
    }

    /**
     * The operations to compute derived values from: the eager-loaded collection when the
     * caller preloaded it (e.g. a listing page avoiding N+1 across many investments), or a
     * fresh query otherwise — always correct even right after a new operation was created.
     *
     * @return Collection<int, InvestmentOperation>
     */
    private function operationsForCalculation(): Collection
    {
        return $this->relationLoaded('operations') ? $this->operations : $this->operations()->get();
    }

    /**
     * Number of shares/quotas currently held (only meaningful for quantity-based types).
     */
    public function currentQuantity(): string
    {
        $operations = $this->operationsForCalculation();
        $bought = (float) $operations->where('type', InvestmentOperationType::Buy)->sum('quantity');
        $sold = (float) $operations->where('type', InvestmentOperationType::Sell)->sum('quantity');

        return (string) ($bought - $sold);
    }

    /**
     * Cash balance from deposits, interest, and withdrawals (only meaningful for fixed income).
     */
    public function currentBalance(): string
    {
        $operations = $this->operationsForCalculation();
        $in = (float) $operations->whereIn('type', [InvestmentOperationType::Deposit, InvestmentOperationType::Interest])->sum('amount');
        $out = (float) $operations->where('type', InvestmentOperationType::Withdrawal)->sum('amount');

        return (string) ($in - $out);
    }

    /**
     * Net capital placed into the position through buys and sells (only meaningful for quantity-based types).
     */
    public function investedAmount(): string
    {
        $operations = $this->operationsForCalculation();
        $bought = (float) $operations->where('type', InvestmentOperationType::Buy)->sum('amount');
        $sold = (float) $operations->where('type', InvestmentOperationType::Sell)->sum('amount');

        return (string) ($bought - $sold);
    }

    /**
     * Average price paid per share still held, based on the net invested amount.
     */
    public function averagePrice(): string
    {
        $quantity = (float) $this->currentQuantity();

        if ($quantity <= 0) {
            return '0';
        }

        return (string) ((float) $this->investedAmount() / $quantity);
    }

    /**
     * Total dividends and interest received to date.
     */
    public function dividendsReceived(): string
    {
        return (string) $this->operationsForCalculation()->whereIn('type', [InvestmentOperationType::Dividend, InvestmentOperationType::Interest])->sum('amount');
    }

    /**
     * Current value of the position, in the investment's own currency.
     */
    public function currentValue(): string
    {
        return $this->type->isQuantityBased()
            ? (string) ((float) $this->currentQuantity() * (float) ($this->current_price ?? $this->averagePrice()))
            : $this->currentBalance();
    }

    /**
     * Percentage change between the current price and the average purchase price.
     * Only meaningful for quantity-based types with a configured current price and prior purchases.
     */
    public function priceChangePercentage(): ?string
    {
        if (! $this->type->isQuantityBased() || $this->current_price === null) {
            return null;
        }

        $averagePrice = (float) $this->averagePrice();

        if ($averagePrice <= 0) {
            return null;
        }

        return (string) ((((float) $this->current_price) - $averagePrice) / $averagePrice * 100);
    }

    /**
     * Capital gain from price movement on the position (realized and unrealized combined).
     * Only meaningful for quantity-based types; fixed income has no price to gain or lose on.
     */
    public function capitalGain(): string
    {
        if (! $this->type->isQuantityBased()) {
            return '0';
        }

        return (string) ((float) $this->currentValue() - (float) $this->investedAmount());
    }

    /**
     * Total return: capital gain plus dividends and interest received.
     */
    public function totalGain(): string
    {
        return (string) ((float) $this->capitalGain() + (float) $this->dividendsReceived());
    }

    /**
     * Whether the position still holds shares/quotas or a balance, based on its operation history.
     * A quantity-based position with nothing left, or fixed income with no balance left, is closed.
     */
    public function isActive(): bool
    {
        return $this->type->isQuantityBased()
            ? (float) $this->currentQuantity() > 0
            : (float) $this->currentBalance() > 0;
    }
}
