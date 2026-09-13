<?php

namespace App\Livewire\RecurringTransactions;

use App\Concerns\ConfirmsDeletion;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringTransaction;
use Flux\Flux;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Transações Recorrentes')]
class Index extends Component
{
    use ConfirmsDeletion;

    public string $type = TransactionType::Expense->value;

    public ?int $account_id = null;

    public ?int $to_account_id = null;

    public ?int $category_id = null;

    public string $description = '';

    public string $amount = '0';

    public string $start_date = '';

    public bool $is_active = true;

    public ?int $editingRecurringTransactionId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', RecurringTransaction::class);

        $this->start_date = now()->toDateString();
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
    public function recurringTransactions(): Collection
    {
        return Auth::user()->recurringTransactions()
            ->with(['account', 'toAccount', 'category'])
            ->orderByDesc('start_date')
            ->get();
    }

    #[Computed]
    public function accounts(): Collection
    {
        return Auth::user()->accounts()->orderBy('name')->get();
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
        $this->authorize('create', RecurringTransaction::class);

        $this->reset('account_id', 'to_account_id', 'category_id', 'description', 'editingRecurringTransactionId');
        $this->type = TransactionType::Expense->value;
        $this->amount = '0';
        $this->start_date = now()->toDateString();
        $this->is_active = true;
        $this->resetValidation();

        Flux::modal('recurring-transaction-form')->show();
    }

    public function edit(RecurringTransaction $recurringTransaction): void
    {
        $this->authorize('update', $recurringTransaction);

        $this->editingRecurringTransactionId = $recurringTransaction->id;
        $this->type = $recurringTransaction->type->value;
        $this->account_id = $recurringTransaction->account_id;
        $this->to_account_id = $recurringTransaction->to_account_id;
        $this->category_id = $recurringTransaction->category_id;
        $this->description = (string) $recurringTransaction->description;
        $this->amount = (string) $recurringTransaction->amount;
        $this->start_date = $recurringTransaction->start_date->toDateString();
        $this->is_active = $recurringTransaction->is_active;
        $this->resetValidation();

        Flux::modal('recurring-transaction-form')->show();
    }

    public function save(): void
    {
        $user = Auth::user();
        $recurringTransaction = null;

        if ($this->editingRecurringTransactionId !== null) {
            $recurringTransaction = $user->recurringTransactions()->findOrFail($this->editingRecurringTransactionId);
            $this->authorize('update', $recurringTransaction);
        } else {
            $this->authorize('create', RecurringTransaction::class);
        }

        $validated = $this->validate();

        if ($validated['type'] === TransactionType::Transfer->value) {
            $validated['category_id'] = null;
        } else {
            $validated['to_account_id'] = null;
        }

        if ($recurringTransaction) {
            $recurringTransaction->update($validated);
            Flux::toast(variant: 'success', text: __('Recurring transaction updated.'));
        } else {
            $recurringTransaction = $user->recurringTransactions()->create($validated);
            Flux::toast(variant: 'success', text: __('Recurring transaction created.'));
        }

        $recurringTransaction->generateDueOccurrences();

        unset($this->recurringTransactions);
        $this->reset('account_id', 'to_account_id', 'category_id', 'description', 'editingRecurringTransactionId');
        $this->type = TransactionType::Expense->value;
        $this->amount = '0';
        $this->start_date = now()->toDateString();
        $this->is_active = true;
        Flux::modal('recurring-transaction-form')->close();
    }

    public function confirmDelete(RecurringTransaction $recurringTransaction): void
    {
        $this->confirmDeletionOf($recurringTransaction);
    }

    protected function findForDeletion(int $id): Model
    {
        return Auth::user()->recurringTransactions()->findOrFail($id);
    }

    protected function deletionModalName(): string
    {
        return 'confirm-recurring-transaction-deletion';
    }

    protected function deletionSuccessMessage(): string
    {
        return __('Recurring transaction deleted.');
    }

    protected function afterDeletion(): void
    {
        unset($this->recurringTransactions);
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
            'amount' => ['required', 'numeric', 'gt:0'],
            'start_date' => ['required', 'date'],
            'is_active' => ['boolean'],
        ];
    }

    public function render()
    {
        return view('livewire.recurring-transactions.index');
    }
}
