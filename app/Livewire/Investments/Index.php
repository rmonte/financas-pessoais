<?php

namespace App\Livewire\Investments;

use App\Concerns\ConfirmsDeletion;
use App\Enums\AssetClass;
use App\Enums\Currency;
use App\Enums\InvestmentOperationType;
use App\Enums\InvestmentType;
use App\Models\Account;
use App\Models\Bank;
use App\Models\Investment;
use App\Models\InvestmentOperation;
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

#[Title('Investimentos')]
class Index extends Component
{
    use ConfirmsDeletion;

    public ?int $bank_id = null;

    public string $type = InvestmentType::Stock->value;

    public string $name = '';

    public string $ticker = '';

    public string $currency = Currency::BRL->value;

    public string $current_price = '';

    public ?int $editingInvestmentId = null;

    public string $statusFilter = 'active';

    public string $operationInvestmentId = '';

    public string $operationType = '';

    public string $operationDate = '';

    public string $operationQuantity = '';

    public string $operationUnitPrice = '';

    public string $operationAmount = '0';

    public string $operationDescription = '';

    public ?int $operationAccountId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Investment::class);
    }

    #[Computed]
    public function investments(): Collection
    {
        return Auth::user()->investments()->with(['bank', 'operations'])->orderBy('name')->get();
    }

    /**
     * The investments to list in the table, filtered by the active/closed tab.
     */
    #[Computed]
    public function filteredInvestments(): Collection
    {
        return $this->investments
            ->filter(fn (Investment $investment) => $investment->isActive() === ($this->statusFilter === 'active'))
            ->values();
    }

    #[Computed]
    public function banks(): Collection
    {
        return Auth::user()->banks()->orderBy('name')->get();
    }

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

    #[Computed]
    public function totalValueInBrl(): string
    {
        $rate = $this->exchangeRate;

        return (string) $this->investments->sum(
            fn (Investment $investment) => $investment->currency->toBrl((float) $investment->currentValue(), $rate)
        );
    }

    /**
     * How the total investment value is allocated across broad asset classes.
     *
     * @return array<int, array{id: string, label: string, value: float, color: string}>
     */
    #[Computed]
    public function allocationByAssetClass(): array
    {
        $rate = $this->exchangeRate;

        return $this->investments
            ->groupBy(fn (Investment $investment) => $investment->type->assetClass()->value)
            ->map(function (Collection $group, string $assetClassValue) use ($rate) {
                $assetClass = AssetClass::from($assetClassValue);

                $value = $group->sum(
                    fn (Investment $investment) => $investment->currency->toBrl((float) $investment->currentValue(), $rate)
                );

                return [
                    'id' => $assetClass->value,
                    'label' => $assetClass->label(),
                    'value' => $value,
                    'color' => $assetClass->color(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The investment type relevant to the operation form: the freshly chosen type when
     * creating a new investment inline, or the existing investment's type otherwise.
     */
    #[Computed]
    public function operationInvestmentType(): ?InvestmentType
    {
        if ($this->operationInvestmentId === '__new__') {
            return InvestmentType::tryFrom($this->type);
        }

        return $this->investments->firstWhere('id', (int) $this->operationInvestmentId)?->type;
    }

    /**
     * @return array<int, InvestmentOperationType>
     */
    #[Computed]
    public function operationTypeOptions(): array
    {
        return $this->operationInvestmentType?->operationTypes() ?? [];
    }

    public function updatedType(): void
    {
        if (! (InvestmentType::tryFrom($this->type)?->isQuantityBased() ?? false)) {
            $this->ticker = '';
            $this->current_price = '';
        }

        if ($this->operationInvestmentId === '__new__') {
            $this->resetOperationTypeFields();
        }
    }

    public function updatedOperationInvestmentId(): void
    {
        $this->reset('bank_id', 'ticker', 'current_price', 'name');
        $this->type = InvestmentType::Stock->value;
        $this->currency = Currency::BRL->value;

        $this->resetOperationTypeFields();
    }

    private function resetOperationTypeFields(): void
    {
        $types = $this->operationTypeOptions;

        $this->operationType = $types[0]->value ?? '';
        $this->operationQuantity = '';
        $this->operationUnitPrice = '';
        $this->operationAmount = '0';
    }

    public function updatedOperationQuantity(): void
    {
        $this->recalculateOperationAmount();
    }

    public function updatedOperationUnitPrice(): void
    {
        $this->recalculateOperationAmount();
    }

    private function recalculateOperationAmount(): void
    {
        $type = InvestmentOperationType::tryFrom($this->operationType);

        if ($type?->isTrade() && is_numeric($this->operationQuantity) && is_numeric($this->operationUnitPrice)) {
            $this->operationAmount = (string) round((float) $this->operationQuantity * (float) $this->operationUnitPrice, 2);
        }
    }

    public function edit(Investment $investment): void
    {
        $this->authorize('update', $investment);

        $this->editingInvestmentId = $investment->id;
        $this->bank_id = $investment->bank_id;
        $this->type = $investment->type->value;
        $this->name = $investment->name;
        $this->ticker = (string) $investment->ticker;
        $this->currency = $investment->currency->value;
        $this->current_price = (string) $investment->current_price;
        $this->resetValidation();

        Flux::modal('investment-form')->show();
    }

    public function save(): void
    {
        $investment = Auth::user()->investments()->findOrFail($this->editingInvestmentId);
        $this->authorize('update', $investment);

        $validated = $this->validate();

        if (! InvestmentType::from($validated['type'])->isQuantityBased()) {
            $validated['ticker'] = null;
            $validated['current_price'] = null;
        }

        $investment->update($validated);
        Flux::toast(variant: 'success', text: __('Investment updated.'));

        unset($this->investments);
        $this->reset('bank_id', 'ticker', 'current_price', 'editingInvestmentId');
        $this->name = '';
        $this->type = InvestmentType::Stock->value;
        $this->currency = Currency::BRL->value;
        Flux::modal('investment-form')->close();
    }

    public function createOperation(): void
    {
        $this->authorize('create', InvestmentOperation::class);

        $this->reset(
            'operationInvestmentId', 'bank_id', 'ticker', 'current_price', 'name',
            'operationQuantity', 'operationUnitPrice', 'operationDescription', 'operationAccountId',
        );
        $this->type = InvestmentType::Stock->value;
        $this->currency = Currency::BRL->value;
        $this->operationType = '';
        $this->operationDate = now()->toDateString();
        $this->operationAmount = '0';
        $this->resetValidation();

        Flux::modal('operation-form')->show();
    }

    public function saveOperation(): void
    {
        $this->authorize('create', InvestmentOperation::class);

        $isNewInvestment = $this->operationInvestmentId === '__new__';

        $validated = $this->validate($this->operationRules());

        $user = Auth::user();

        if ($isNewInvestment) {
            $investmentType = InvestmentType::from($validated['type']);
            $isTrade = InvestmentOperationType::from($validated['operationType'])->isTrade();

            $investment = $user->investments()->create([
                'bank_id' => $validated['bank_id'],
                'type' => $validated['type'],
                'name' => $validated['name'],
                'ticker' => $investmentType->isQuantityBased() ? $validated['ticker'] : null,
                'currency' => $validated['currency'],
                'current_price' => $investmentType->isQuantityBased() && $isTrade ? $validated['operationUnitPrice'] : null,
            ]);
        } else {
            $investment = $user->investments()->findOrFail((int) $this->operationInvestmentId);
        }

        if (! InvestmentOperationType::from($validated['operationType'])->isTrade()) {
            $validated['operationQuantity'] = null;
            $validated['operationUnitPrice'] = null;
        }

        $operation = $investment->operations()->create([
            'user_id' => $user->id,
            'account_id' => $validated['operationAccountId'],
            'type' => $validated['operationType'],
            'date' => $validated['operationDate'],
            'quantity' => $validated['operationQuantity'],
            'unit_price' => $validated['operationUnitPrice'],
            'amount' => $validated['operationAmount'],
            'description' => $validated['operationDescription'],
        ]);

        $operation->syncLinkedTransaction();

        unset($this->investments);
        $this->reset(
            'operationInvestmentId', 'bank_id', 'ticker', 'current_price', 'name',
            'operationQuantity', 'operationUnitPrice', 'operationDescription', 'operationAccountId',
        );
        $this->type = InvestmentType::Stock->value;
        $this->currency = Currency::BRL->value;
        $this->operationType = '';
        $this->operationDate = now()->toDateString();
        $this->operationAmount = '0';
        Flux::toast(variant: 'success', text: __('Operation created.'));
        Flux::modal('operation-form')->close();
    }

    public function confirmDelete(Investment $investment): void
    {
        $this->confirmDeletionOf($investment);
    }

    protected function findForDeletion(int $id): Model
    {
        return Auth::user()->investments()->findOrFail($id);
    }

    protected function deletionModalName(): string
    {
        return 'confirm-investment-deletion';
    }

    protected function deletionSuccessMessage(): string
    {
        return __('Investment deleted.');
    }

    protected function afterDeletion(): void
    {
        unset($this->investments);
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function rules(): array
    {
        $existsBank = Rule::exists(Bank::class, 'id')->where(fn ($query) => $query->where('user_id', Auth::id()));

        $isQuantityBased = InvestmentType::tryFrom($this->type)?->isQuantityBased() ?? false;

        return [
            'bank_id' => ['nullable', 'integer', $existsBank],
            'type' => ['required', Rule::enum(InvestmentType::class)],
            'name' => ['required', 'string', 'max:150'],
            'ticker' => [Rule::requiredIf($isQuantityBased), 'nullable', 'string', 'max:20'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'current_price' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    private function operationRules(): array
    {
        $isNewInvestment = $this->operationInvestmentId === '__new__';

        $investmentType = $isNewInvestment ? InvestmentType::tryFrom($this->type) : $this->operationInvestmentType;
        $isQuantityBased = $investmentType?->isQuantityBased() ?? false;
        $isTrade = InvestmentOperationType::tryFrom($this->operationType)?->isTrade() ?? false;

        $rules = [
            'operationInvestmentId' => ['required', 'string'],
            'operationType' => ['required', Rule::enum(InvestmentOperationType::class)->only($investmentType?->operationTypes() ?? [])],
            'operationDate' => ['required', 'date'],
            'operationQuantity' => [Rule::requiredIf($isTrade), 'nullable', 'numeric', 'gt:0'],
            'operationUnitPrice' => [Rule::requiredIf($isTrade), 'nullable', 'numeric', 'gt:0'],
            'operationAmount' => ['required', 'numeric', 'gt:0'],
            'operationDescription' => ['nullable', 'string', 'max:255'],
            'operationAccountId' => [
                'nullable', 'integer',
                Rule::exists(Account::class, 'id')->where(fn ($query) => $query->where('user_id', Auth::id())),
            ],
        ];

        if ($isNewInvestment) {
            $rules += [
                'type' => ['required', Rule::enum(InvestmentType::class)],
                'name' => ['required', 'string', 'max:150'],
                'bank_id' => [
                    'nullable', 'integer',
                    Rule::exists(Bank::class, 'id')->where(fn ($query) => $query->where('user_id', Auth::id())),
                ],
                'ticker' => [Rule::requiredIf($isQuantityBased), 'nullable', 'string', 'max:20'],
                'currency' => ['required', Rule::enum(Currency::class)],
            ];
        } else {
            $rules['operationInvestmentId'] = [
                'required',
                Rule::exists(Investment::class, 'id')->where(fn ($query) => $query->where('user_id', Auth::id())),
            ];
        }

        return $rules;
    }

    public function render()
    {
        return view('livewire.investments.index');
    }
}
