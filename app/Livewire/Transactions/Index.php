<?php

namespace App\Livewire\Transactions;

use App\Concerns\ConfirmsDeletion;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Flux\Flux;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Transações')]
class Index extends Component
{
    use ConfirmsDeletion;

    public string $type = TransactionType::Expense->value;

    public ?int $account_id = null;

    public ?int $to_account_id = null;

    public ?int $category_id = null;

    public string $description = '';

    public string $date = '';

    public string $amount = '0';

    public int $installments = 1;

    public ?int $editingTransactionId = null;

    public string $filterType = '';

    public ?int $filterAccountId = null;

    public ?int $filterCategoryId = null;

    public int $filterYear = 0;

    public int $filterMonth = 0;

    public function mount(): void
    {
        $this->authorize('viewAny', Transaction::class);

        $this->date = now()->toDateString();
        $this->filterYear = now()->year;
        $this->filterMonth = now()->month;
    }

    public function resetFilters(): void
    {
        $this->reset('filterType', 'filterAccountId', 'filterCategoryId');
        $this->filterYear = now()->year;
        $this->filterMonth = now()->month;
    }

    public function updatedType(): void
    {
        if ($this->type === TransactionType::Transfer->value) {
            $this->category_id = null;
        } else {
            $this->to_account_id = null;
        }
    }

    #[Computed]
    public function transactions(): Collection
    {
        return Auth::user()->transactions()
            ->with(['account', 'toAccount', 'category', 'investmentOperation.investment'])
            ->when($this->filterType !== '', fn ($query) => $query->where('type', $this->filterType))
            ->when($this->filterAccountId !== null, fn ($query) => $query->where(
                fn ($query) => $query->where('account_id', $this->filterAccountId)
                    ->orWhere('to_account_id', $this->filterAccountId)
            ))
            ->when($this->filterCategoryId !== null, fn ($query) => $query->where('category_id', $this->filterCategoryId))
            ->whereYear('date', $this->filterYear)
            ->whereMonth('date', $this->filterMonth)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function totals(): array
    {
        $transactions = $this->transactions;

        $income = (float) $transactions->where('type', TransactionType::Income)->sum('amount');
        $expense = (float) $transactions->where('type', TransactionType::Expense)->sum('amount');

        return [
            'income' => (string) $income,
            'expense' => (string) $expense,
            'transfer' => (string) $transactions->where('type', TransactionType::Transfer)->sum('amount'),
            'investment' => (string) $transactions->where('type', TransactionType::Investment)->sum('amount'),
            'balance' => (string) ($income - $expense),
        ];
    }

    /**
     * @return array<int, TransactionType>
     */
    #[Computed]
    public function manualTransactionTypes(): array
    {
        return TransactionType::manuallyCreatable();
    }

    #[Computed]
    public function accounts(): Collection
    {
        return Auth::user()->accounts()->orderBy('name')->get();
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function years(): array
    {
        $current = now()->year;

        return range($current + 1, $current - 5);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function months(): array
    {
        return collect(range(1, 12))
            ->mapWithKeys(fn (int $month) => [$month => ucfirst(Carbon::create(2000, $month, 1)->translatedFormat('F'))])
            ->all();
    }

    #[Computed]
    public function allCategories(): Collection
    {
        return Auth::user()->categories()->orderBy('name')->get();
    }

    #[Computed]
    public function categories(): Collection
    {
        if ($this->type === TransactionType::Transfer->value) {
            return collect();
        }

        return Auth::user()->categories()->where('type', $this->type)->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorize('create', Transaction::class);

        $this->reset('account_id', 'to_account_id', 'category_id', 'description', 'editingTransactionId');
        $this->type = TransactionType::Expense->value;
        $this->date = now()->toDateString();
        $this->amount = '0';
        $this->installments = 1;
        $this->resetValidation();

        Flux::modal('transaction-form')->show();
    }

    public function edit(Transaction $transaction): void
    {
        $this->authorize('update', $transaction);

        $this->editingTransactionId = $transaction->id;
        $this->type = $transaction->type->value;
        $this->account_id = $transaction->account_id;
        $this->to_account_id = $transaction->to_account_id;
        $this->category_id = $transaction->category_id;
        $this->description = (string) $transaction->description;
        $this->date = $transaction->date->toDateString();
        $this->amount = (string) $transaction->amount;
        $this->resetValidation();

        Flux::modal('transaction-form')->show();
    }

    public function save(): void
    {
        $user = Auth::user();
        $transaction = null;

        if ($this->editingTransactionId !== null) {
            $transaction = $user->transactions()->findOrFail($this->editingTransactionId);
            $this->authorize('update', $transaction);
        } else {
            $this->authorize('create', Transaction::class);
        }

        $validated = $this->validate();
        unset($validated['installments']);

        if ($validated['type'] === TransactionType::Transfer->value) {
            $validated['category_id'] = null;
        } else {
            $validated['to_account_id'] = null;
        }

        if (! $transaction && $validated['type'] === TransactionType::Expense->value && $this->installments > 1) {
            Transaction::createInstallments(
                $user,
                Arr::only($validated, ['account_id', 'category_id', 'description']),
                (float) $validated['amount'],
                Carbon::parse($validated['date']),
                $this->installments,
            );
            Flux::toast(variant: 'success', text: __('Installments created.'));
        } else {
            $validated['invoice_id'] = Transaction::resolveInvoiceId(
                Account::find($validated['account_id']),
                TransactionType::from($validated['type']),
                Carbon::parse($validated['date']),
            );

            if ($transaction) {
                $transaction->update($validated);
                Flux::toast(variant: 'success', text: __('Transaction updated.'));
            } else {
                $user->transactions()->create($validated);
                Flux::toast(variant: 'success', text: __('Transaction created.'));
            }
        }

        unset($this->transactions);
        $this->reset('account_id', 'to_account_id', 'category_id', 'description', 'editingTransactionId');
        $this->type = TransactionType::Expense->value;
        $this->date = now()->toDateString();
        $this->amount = '0';
        $this->installments = 1;
        Flux::modal('transaction-form')->close();
    }

    public function confirmDelete(Transaction $transaction): void
    {
        $this->confirmDeletionOf($transaction);
    }

    protected function findForDeletion(int $id): Model
    {
        return Auth::user()->transactions()->findOrFail($id);
    }

    protected function deletionModalName(): string
    {
        return 'confirm-transaction-deletion';
    }

    protected function deletionSuccessMessage(): string
    {
        return __('Transaction deleted.');
    }

    protected function afterDeletion(): void
    {
        unset($this->transactions);
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function rules(): array
    {
        $existsAccount = Rule::exists(Account::class, 'id')->where(fn ($query) => $query->where('user_id', Auth::id()));

        $isTransfer = $this->type === TransactionType::Transfer->value;

        return [
            'type' => ['required', Rule::enum(TransactionType::class)->only(TransactionType::manuallyCreatable())],
            'account_id' => ['required', 'integer', $existsAccount],
            'to_account_id' => [
                Rule::requiredIf($isTransfer),
                'nullable',
                'integer',
                $existsAccount,
                Rule::notIn(array_filter([$this->account_id])),
            ],
            'category_id' => [
                Rule::requiredIf(! $isTransfer),
                'nullable',
                'integer',
                Rule::exists(Category::class, 'id')->where(fn ($query) => $query->where('user_id', Auth::id())->where('type', $this->type)),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'installments' => ['required', 'integer', 'min:1', 'max:60'],
        ];
    }

    public function render()
    {
        return view('livewire.transactions.index');
    }
}
