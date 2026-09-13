<?php

namespace App\Livewire\Investments;

use App\Enums\InvestmentOperationType;
use App\Models\Account;
use App\Models\Investment;
use App\Models\InvestmentOperation;
use App\Services\ExchangeRateService;
use Flux\Flux;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, InvestmentOperation> $operations
 * @property-read array<int, InvestmentOperationType> $operationTypes
 * @property-read Collection<int, Account> $accounts
 * @property-read float $exchangeRate
 */
#[Title('Investimento')]
class Show extends Component
{
    public Investment $investment;

    public string $type = '';

    public string $date = '';

    public string $quantity = '';

    public string $unit_price = '';

    public string $amount = '0';

    public string $description = '';

    public ?int $account_id = null;

    public ?int $editingOperationId = null;

    public ?int $deletingOperationId = null;

    public function mount(Investment $investment): void
    {
        $this->authorize('view', $investment);

        $this->investment = $investment->load('operations');
        $this->type = $investment->type->operationTypes()[0]->value;
        $this->date = now()->toDateString();
    }

    /**
     * @return Collection<int, InvestmentOperation>
     */
    #[Computed]
    public function operations(): Collection
    {
        return $this->investment->operations()->with('account')->orderByDesc('date')->orderByDesc('id')->get();
    }

    /**
     * @return array<int, InvestmentOperationType>
     */
    #[Computed]
    public function operationTypes(): array
    {
        return $this->investment->type->operationTypes();
    }

    /**
     * @return Collection<int, Account>
     */
    #[Computed]
    public function accounts(): Collection
    {
        return Auth::user()->accounts()->orderBy('name')->get();
    }

    #[Computed]
    public function exchangeRate(): float
    {
        return app(ExchangeRateService::class)->usdToBrl();
    }

    public function updatedQuantity(): void
    {
        $this->recalculateAmount();
    }

    public function updatedUnitPrice(): void
    {
        $this->recalculateAmount();
    }

    private function recalculateAmount(): void
    {
        $type = InvestmentOperationType::tryFrom($this->type);

        if ($type?->isTrade() && is_numeric($this->quantity) && is_numeric($this->unit_price)) {
            $this->amount = (string) round((float) $this->quantity * (float) $this->unit_price, 2);
        }
    }

    public function create(): void
    {
        $this->authorize('create', InvestmentOperation::class);

        $this->reset('quantity', 'unit_price', 'description', 'account_id', 'editingOperationId');
        $this->type = $this->investment->type->operationTypes()[0]->value;
        $this->date = now()->toDateString();
        $this->amount = '0';
        $this->resetValidation();

        Flux::modal('operation-form')->show();
    }

    public function edit(InvestmentOperation $operation): void
    {
        $this->authorize('update', $operation);

        $this->editingOperationId = $operation->id;
        $this->type = $operation->type->value;
        $this->date = $operation->date->toDateString();
        $this->quantity = (string) $operation->quantity;
        $this->unit_price = (string) $operation->unit_price;
        $this->amount = (string) $operation->amount;
        $this->description = (string) $operation->description;
        $this->account_id = $operation->account_id;
        $this->resetValidation();

        Flux::modal('operation-form')->show();
    }

    public function save(): void
    {
        $operation = null;

        if ($this->editingOperationId !== null) {
            $operation = $this->investment->operations()->findOrFail($this->editingOperationId);
            $this->authorize('update', $operation);
        } else {
            $this->authorize('create', InvestmentOperation::class);
        }

        $validated = $this->validate();

        if (! InvestmentOperationType::from($validated['type'])->isTrade()) {
            $validated['quantity'] = null;
            $validated['unit_price'] = null;
        }

        if ($operation) {
            $operation->update($validated);
            Flux::toast(variant: 'success', text: __('Operation updated.'));
        } else {
            $operation = $this->investment->operations()->create([...$validated, 'user_id' => Auth::id()]);
            Flux::toast(variant: 'success', text: __('Operation created.'));
        }

        $operation->syncLinkedTransaction();

        $this->investment->unsetRelation('operations');
        unset($this->operations);
        $this->reset('quantity', 'unit_price', 'description', 'account_id', 'editingOperationId');
        $this->type = $this->investment->type->operationTypes()[0]->value;
        $this->date = now()->toDateString();
        $this->amount = '0';
        Flux::modal('operation-form')->close();
    }

    public function confirmDelete(InvestmentOperation $operation): void
    {
        $this->authorize('delete', $operation);

        $this->deletingOperationId = $operation->id;

        Flux::modal('confirm-operation-deletion')->show();
    }

    public function delete(): void
    {
        $operation = $this->investment->operations()->findOrFail($this->deletingOperationId);

        $this->authorize('delete', $operation);

        $operation->delete();

        $this->investment->unsetRelation('operations');
        unset($this->operations);
        $this->reset('deletingOperationId');
        Flux::modal('confirm-operation-deletion')->close();
        Flux::toast(variant: 'success', text: __('Operation deleted.'));
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function rules(): array
    {
        $isTrade = InvestmentOperationType::tryFrom($this->type)?->isTrade() ?? false;

        return [
            'type' => ['required', Rule::enum(InvestmentOperationType::class)->only($this->investment->type->operationTypes())],
            'date' => ['required', 'date'],
            'quantity' => [Rule::requiredIf($isTrade), 'nullable', 'numeric', 'gt:0'],
            'unit_price' => [Rule::requiredIf($isTrade), 'nullable', 'numeric', 'gt:0'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'account_id' => [
                'nullable', 'integer',
                Rule::exists(Account::class, 'id')->where(fn ($query) => $query->where('user_id', Auth::id())),
            ],
        ];
    }

    public function render(): View
    {
        return view('livewire.investments.show');
    }
}
