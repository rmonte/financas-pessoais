<?php

namespace App\Livewire\Accounts;

use App\Concerns\ConfirmsDeletion;
use App\Enums\Currency;
use App\Models\Account;
use App\Models\Bank;
use App\Services\ExchangeRateService;
use Flux\Flux;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Contas')]
class Index extends Component
{
    use ConfirmsDeletion;

    public string $name = '';

    public ?int $bank_id = null;

    public string $currency = Currency::BRL->value;

    public string $description = '';

    public string $initial_balance = '0';

    public bool $is_active = true;

    public bool $is_credit_card = false;

    public string $credit_limit = '';

    public ?int $closing_day = null;

    public ?int $due_day = null;

    public ?int $editingAccountId = null;

    public bool $creatingBank = false;

    public string $newBankName = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Account::class);
    }

    #[Computed]
    public function accounts(): Collection
    {
        return Auth::user()->accounts()->with(['bank', 'transactions.investmentOperation', 'incomingTransfers'])->orderBy('name')->get();
    }

    #[Computed]
    public function banks(): Collection
    {
        return Auth::user()->banks()->orderBy('name')->get();
    }

    #[Computed]
    public function exchangeRate(): float
    {
        return app(ExchangeRateService::class)->usdToBrl();
    }

    /**
     * Combined balance of active accounts, converted to BRL.
     */
    #[Computed]
    public function totalBalanceInBrl(): string
    {
        $activeAccounts = $this->accounts->where('is_active', true);
        $hasUsdAccount = $activeAccounts->contains(fn (Account $account) => $account->currency === Currency::USD);
        $rate = $hasUsdAccount ? $this->exchangeRate : 1.0;

        return (string) $activeAccounts->sum(
            fn (Account $account) => $account->currency->toBrl((float) $account->currentBalance(), $rate)
        );
    }

    #[Computed]
    public function activeAccountsCount(): int
    {
        return $this->accounts->where('is_active', true)->count();
    }

    public function create(): void
    {
        $this->authorize('create', Account::class);

        $this->reset('name', 'bank_id', 'description', 'editingAccountId', 'closing_day', 'due_day', 'creatingBank', 'newBankName');
        $this->currency = Currency::BRL->value;
        $this->initial_balance = '0';
        $this->is_active = true;
        $this->is_credit_card = false;
        $this->credit_limit = '';
        $this->resetValidation();

        Flux::modal('account-form')->show();
    }

    public function edit(Account $account): void
    {
        $this->authorize('update', $account);

        $this->editingAccountId = $account->id;
        $this->name = $account->name;
        $this->bank_id = $account->bank_id;
        $this->currency = $account->currency->value;
        $this->description = (string) $account->description;
        $this->initial_balance = (string) $account->initial_balance;
        $this->is_active = $account->is_active;
        $this->is_credit_card = $account->is_credit_card;
        $this->credit_limit = (string) $account->credit_limit;
        $this->closing_day = $account->closing_day;
        $this->due_day = $account->due_day;
        $this->creatingBank = false;
        $this->newBankName = '';
        $this->resetValidation();

        Flux::modal('account-form')->show();
    }

    public function save(): void
    {
        $user = Auth::user();
        $account = null;

        if ($this->editingAccountId !== null) {
            $account = $user->accounts()->findOrFail($this->editingAccountId);
            $this->authorize('update', $account);
        } else {
            $this->authorize('create', Account::class);
        }

        $validated = $this->validate();

        if (! $validated['is_credit_card']) {
            $validated['credit_limit'] = null;
            $validated['closing_day'] = null;
            $validated['due_day'] = null;
        }

        if ($account) {
            $account->update($validated);
            Flux::toast(variant: 'success', text: __('Account updated.'));
        } else {
            $user->accounts()->create($validated);
            Flux::toast(variant: 'success', text: __('Account created.'));
        }

        unset($this->accounts);
        $this->reset('name', 'bank_id', 'description', 'editingAccountId', 'closing_day', 'due_day', 'creatingBank', 'newBankName');
        $this->currency = Currency::BRL->value;
        $this->initial_balance = '0';
        $this->is_active = true;
        $this->is_credit_card = false;
        $this->credit_limit = '';
        Flux::modal('account-form')->close();
    }

    public function startCreatingBank(): void
    {
        $this->authorize('create', Bank::class);

        $this->creatingBank = true;
        $this->newBankName = '';
        $this->resetValidation('newBankName');
    }

    public function cancelCreatingBank(): void
    {
        $this->creatingBank = false;
        $this->newBankName = '';
        $this->resetValidation('newBankName');
    }

    public function saveBank(): void
    {
        $this->authorize('create', Bank::class);

        $validated = $this->validate([
            'newBankName' => [
                'required', 'string', 'max:100',
                Rule::unique(Bank::class, 'name')->where(fn ($query) => $query->where('user_id', Auth::id())),
            ],
        ]);

        $bank = Auth::user()->banks()->create(['name' => $validated['newBankName']]);

        unset($this->banks);
        $this->bank_id = $bank->id;
        $this->creatingBank = false;
        $this->newBankName = '';
        Flux::toast(variant: 'success', text: __('Bank created.'));
    }

    public function confirmDelete(Account $account): void
    {
        $this->confirmDeletionOf($account);
    }

    protected function findForDeletion(int $id): Model
    {
        return Auth::user()->accounts()->findOrFail($id);
    }

    protected function deletionModalName(): string
    {
        return 'confirm-account-deletion';
    }

    protected function deletionSuccessMessage(): string
    {
        return __('Account deleted.');
    }

    protected function afterDeletion(): void
    {
        unset($this->accounts);
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function rules(): array
    {
        $uniqueName = Rule::unique(Account::class)->where(fn ($query) => $query->where('user_id', Auth::id()));

        if ($this->editingAccountId !== null) {
            $uniqueName = $uniqueName->ignore($this->editingAccountId);
        }

        $existsBank = Rule::exists(Bank::class, 'id')->where(fn ($query) => $query->where('user_id', Auth::id()));

        return [
            'name' => ['required', 'string', 'max:100', $uniqueName],
            'bank_id' => ['nullable', 'integer', $existsBank],
            'currency' => ['required', Rule::enum(Currency::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'initial_balance' => ['required', 'numeric'],
            'is_active' => ['boolean'],
            'is_credit_card' => ['boolean'],
            'credit_limit' => [Rule::requiredIf($this->is_credit_card), 'nullable', 'numeric', 'gt:0'],
            'closing_day' => [Rule::requiredIf($this->is_credit_card), 'nullable', 'integer', 'between:1,31'],
            'due_day' => [Rule::requiredIf($this->is_credit_card), 'nullable', 'integer', 'between:1,31'],
        ];
    }

    public function render()
    {
        return view('livewire.accounts.index');
    }
}
