<?php

namespace App\Livewire\Dashboard;

use App\Enums\Currency;
use App\Enums\InvestmentOperationType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Investment;
use App\Models\InvestmentOperation;
use App\Models\Invoice;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Services\ExchangeRateService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, Account> $accounts
 * @property-read string $totalBalance
 * @property-read string $accountsTotalValue
 * @property-read Collection<int, Investment> $investments
 * @property-read float $exchangeRate
 * @property-read string $investmentsTotalValue
 * @property-read array<string, string> $monthSummary
 * @property-read Collection<int, CarbonInterface> $evolutionYears
 * @property-read array<int, array{year: string, balance: float}> $patrimonyEvolution
 * @property-read array<int, array{month: string, income: float, expense: float}> $incomeExpenseByMonth
 * @property-read array<int, array{id: string, label: string, value: float, color: string}> $expenseByCategory
 * @property-read Collection<int, Invoice> $upcomingInvoices
 * @property-read Collection<int, RecurringTransaction> $upcomingRecurringTransactions
 */
#[Title('Painel')]
class Index extends Component
{
    /**
     * @var array<int, string>
     */
    private const CATEGORY_PALETTE = [
        'blue', 'emerald', 'amber', 'rose', 'violet', 'cyan',
        'orange', 'teal', 'fuchsia', 'lime', 'indigo', 'pink',
    ];

    public function mount(): void
    {
        // Primarily handled by the scheduled app:generate-recurring-transactions command;
        // this keeps the dashboard fresh even if the scheduler isn't running yet.
        RecurringTransaction::generateDueForUser(Auth::user());
    }

    /**
     * @return Collection<int, Account>
     */
    #[Computed]
    public function accounts(): Collection
    {
        return Auth::user()->accounts()->where('is_active', true)
            ->with(['bank', 'transactions.investmentOperation', 'incomingTransfers'])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function totalBalance(): string
    {
        return (string) ((float) $this->accountsTotalValue + (float) $this->investmentsTotalValue);
    }

    #[Computed]
    public function accountsTotalValue(): string
    {
        $hasUsdAccount = $this->accounts->contains(fn (Account $account) => $account->currency === Currency::USD);
        $rate = $hasUsdAccount ? $this->exchangeRate : 1.0;

        return (string) $this->accounts->sum(
            fn (Account $account) => $account->currency->toBrl((float) $account->currentBalance(), $rate)
        );
    }

    /**
     * @return Collection<int, Investment>
     */
    #[Computed]
    public function investments(): Collection
    {
        return Auth::user()->investments()
            ->with(['bank', 'operations'])
            ->orderBy('name')
            ->get()
            ->filter(fn (Investment $investment) => $investment->isActive())
            ->values();
    }

    #[Computed]
    public function exchangeRate(): float
    {
        return app(ExchangeRateService::class)->usdToBrl();
    }

    #[Computed]
    public function investmentsTotalValue(): string
    {
        $hasUsdInvestment = $this->investments->contains(fn (Investment $investment) => $investment->currency === Currency::USD);
        $rate = $hasUsdInvestment ? $this->exchangeRate : 1.0;

        return (string) $this->investments->sum(
            fn (Investment $investment) => $investment->currency->toBrl((float) $investment->currentValue(), $rate)
        );
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function monthSummary(): array
    {
        $transactions = Auth::user()->transactions()
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->get();

        $income = (float) $transactions->where('type', TransactionType::Income)->sum('amount');
        $expense = (float) $transactions->where('type', TransactionType::Expense)->sum('amount');

        return [
            'income' => (string) $income,
            'expense' => (string) $expense,
            'balance' => (string) ($income - $expense),
        ];
    }

    /**
     * A snapshot point for each of the last 6 years: the end of the year, or "now"
     * (end of the current month) for the current, still-unfinished year.
     *
     * @return Collection<int, CarbonInterface>
     */
    #[Computed]
    public function evolutionYears(): Collection
    {
        $currentYear = now()->year;

        /** @var Collection<int, CarbonInterface> $years */
        $years = collect(range(5, 0))->map(function (int $i) use ($currentYear): CarbonInterface {
            $year = $currentYear - $i;

            return $year === $currentYear
                ? now()->startOfMonth()
                : Carbon::create($year, 12, 1)->startOfMonth();
        });

        return $years;
    }

    /**
     * Total patrimony (accounts + investments) at the end of each of the last 6 years.
     *
     * @return array<int, array{year: string, balance: float}>
     */
    #[Computed]
    public function patrimonyEvolution(): array
    {
        $years = $this->evolutionYears;

        $accountsSeries = $this->accountsBalanceByMonth($years);
        $investmentsSeries = $this->investmentsValueByMonth($years);

        return $years->map(fn (CarbonInterface $year, int $index) => [
            'year' => $year->format('Y'),
            'balance' => round($accountsSeries[$index] + $investmentsSeries[$index], 2),
        ])->values()->all();
    }

    /**
     * Total balance of active accounts, in BRL, at the end of each given month.
     *
     * @param  Collection<int, CarbonInterface>  $months
     * @return array<int, float>
     */
    private function accountsBalanceByMonth(Collection $months): array
    {
        $accountIds = $this->accounts->pluck('id');
        $accountCurrencies = $this->accounts->pluck('currency', 'id');

        $brlBalance = (float) $this->accounts->where('currency', Currency::BRL)->sum('initial_balance');
        $usdBalance = (float) $this->accounts->where('currency', Currency::USD)->sum('initial_balance');

        $hasUsdAccount = $accountCurrencies->contains(Currency::USD);
        $rate = $hasUsdAccount ? $this->exchangeRate : 1.0;

        $transactions = Transaction::query()
            ->where('user_id', Auth::id())
            ->where(fn ($query) => $query->whereIn('account_id', $accountIds)->orWhereIn('to_account_id', $accountIds))
            ->where('date', '<=', $months->last()->copy()->endOfMonth())
            ->orderBy('date')
            ->with('investmentOperation:id,type')
            ->get(['id', 'account_id', 'to_account_id', 'type', 'amount', 'date', 'investment_operation_id']);

        $cursor = 0;
        $count = $transactions->count();

        return $months->map(function (CarbonInterface $month) use (&$cursor, $transactions, $count, $accountIds, $accountCurrencies, $rate, &$brlBalance, &$usdBalance) {
            $endOfMonth = $month->copy()->endOfMonth();

            while ($cursor < $count && $transactions[$cursor]->date->lte($endOfMonth)) {
                $transaction = $transactions[$cursor];
                $amount = (float) $transaction->amount;

                if ($accountIds->contains($transaction->account_id)) {
                    $delta = match ($transaction->type) {
                        TransactionType::Income => $amount,
                        TransactionType::Expense, TransactionType::Transfer => -$amount,
                        default => $transaction->investmentOperation->type->isOutgoing() ? -$amount : $amount,
                    };

                    if (($accountCurrencies[$transaction->account_id] ?? Currency::BRL) === Currency::USD) {
                        $usdBalance += $delta;
                    } else {
                        $brlBalance += $delta;
                    }
                }

                if ($transaction->type === TransactionType::Transfer && $accountIds->contains($transaction->to_account_id)) {
                    if (($accountCurrencies[$transaction->to_account_id] ?? Currency::BRL) === Currency::USD) {
                        $usdBalance += $amount;
                    } else {
                        $brlBalance += $amount;
                    }
                }

                $cursor++;
            }

            return $brlBalance + $usdBalance * $rate;
        })->values()->all();
    }

    /**
     * Total investments value, in BRL, at the end of each given month.
     *
     * Stocks are valued at their currently configured price throughout the whole series
     * (no historical price history is tracked), so this reflects position size growth
     * more accurately than past price swings.
     *
     * @param  Collection<int, CarbonInterface>  $months
     * @return array<int, float>
     */
    private function investmentsValueByMonth(Collection $months): array
    {
        $investments = Auth::user()->investments()->get();

        if ($investments->isEmpty()) {
            return array_fill(0, $months->count(), 0.0);
        }

        $hasUsdInvestment = $investments->contains(fn (Investment $investment) => $investment->currency === Currency::USD);
        $rate = $hasUsdInvestment ? $this->exchangeRate : 1.0;

        $operations = InvestmentOperation::query()
            ->whereIn('investment_id', $investments->pluck('id'))
            ->where('date', '<=', $months->last()->copy()->endOfMonth())
            ->orderBy('date')
            ->get(['investment_id', 'type', 'quantity', 'amount', 'date']);

        $quantities = $investments->pluck('id')->mapWithKeys(fn (int $id) => [$id => 0.0])->all();
        $balances = $quantities;

        $cursor = 0;
        $count = $operations->count();

        return $months->map(function (CarbonInterface $month) use (&$cursor, $operations, $count, &$quantities, &$balances, $investments, $rate) {
            $endOfMonth = $month->copy()->endOfMonth();

            while ($cursor < $count && $operations[$cursor]->date->lte($endOfMonth)) {
                $operation = $operations[$cursor];
                $investmentId = $operation->investment_id;
                $amount = (float) $operation->amount;
                $quantity = (float) $operation->quantity;

                match ($operation->type) {
                    InvestmentOperationType::Buy => $quantities[$investmentId] += $quantity,
                    InvestmentOperationType::Sell => $quantities[$investmentId] -= $quantity,
                    InvestmentOperationType::Deposit, InvestmentOperationType::Interest => $balances[$investmentId] += $amount,
                    InvestmentOperationType::Withdrawal => $balances[$investmentId] -= $amount,
                    default => null,
                };

                $cursor++;
            }

            return $investments->sum(function (Investment $investment) use ($quantities, $balances, $rate) {
                $value = $investment->type->isQuantityBased()
                    ? $quantities[$investment->id] * (float) ($investment->current_price ?? $investment->averagePrice())
                    : $balances[$investment->id];

                return $investment->currency->toBrl($value, $rate);
            });
        })->values()->all();
    }

    /**
     * Income and expense totals for each of the last 6 months.
     *
     * @return array<int, array{month: string, income: float, expense: float}>
     */
    #[Computed]
    public function incomeExpenseByMonth(): array
    {
        $months = collect(range(5, 0))->map(fn (int $i) => now()->subMonthsNoOverflow($i)->startOfMonth());

        $transactions = Auth::user()->transactions()
            ->whereIn('type', [TransactionType::Income, TransactionType::Expense])
            ->where('date', '>=', $months->first())
            ->where('date', '<=', now()->endOfMonth())
            ->get(['type', 'amount', 'date']);

        return $months->map(function (CarbonInterface $month) use ($transactions) {
            $endOfMonth = $month->copy()->endOfMonth();
            $inMonth = $transactions->filter(fn (Transaction $transaction) => $transaction->date->between($month, $endOfMonth));

            return [
                'month' => ucfirst($month->translatedFormat('M/y')),
                'income' => round((float) $inMonth->where('type', TransactionType::Income)->sum('amount'), 2),
                'expense' => round((float) $inMonth->where('type', TransactionType::Expense)->sum('amount'), 2),
            ];
        })->values()->all();
    }

    /**
     * Expenses grouped by category for the current month.
     *
     * @return array<int, array{id: string, label: string, value: float, color: string}>
     */
    #[Computed]
    public function expenseByCategory(): array
    {
        return Auth::user()->transactions()
            ->where('type', TransactionType::Expense)
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->with('category')
            ->orderByDesc('total')
            ->get()
            ->values()
            ->map(fn (Transaction $row, int $index) => [
                'id' => (string) ($row->category_id ?? 'uncategorized'),
                'label' => $row->category !== null ? $row->category->name : __('Uncategorized'),
                'value' => (float) $row->getAttribute('total'),
                'color' => self::CATEGORY_PALETTE[$index % count(self::CATEGORY_PALETTE)],
            ])
            ->all();
    }

    /**
     * The next open invoices due, soonest first.
     *
     * @return Collection<int, Invoice>
     */
    #[Computed]
    public function upcomingInvoices(): Collection
    {
        return Auth::user()->invoices()
            ->whereNull('payment_transaction_id')
            ->with(['account', 'transactions'])
            ->orderBy('due_date')
            ->limit(5)
            ->get();
    }

    /**
     * The active recurring transactions, ordered by their next occurrence date.
     *
     * @return Collection<int, RecurringTransaction>
     */
    #[Computed]
    public function upcomingRecurringTransactions(): Collection
    {
        return Auth::user()->recurringTransactions()
            ->where('is_active', true)
            ->with(['account', 'category'])
            ->get()
            ->map(function (RecurringTransaction $recurringTransaction) {
                $lastDate = $recurringTransaction->transactions()->max('date');

                $recurringTransaction->next_occurrence = $lastDate !== null
                    ? Carbon::parse($lastDate)->addMonthNoOverflow()
                    : $recurringTransaction->start_date->copy();

                return $recurringTransaction;
            })
            ->sortBy('next_occurrence')
            ->take(5)
            ->values();
    }

    public function render(): View
    {
        return view('livewire.dashboard.index');
    }
}
