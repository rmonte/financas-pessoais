<?php

namespace App\Livewire\Banks;

use App\Concerns\ConfirmsDeletion;
use App\Models\Bank;
use Flux\Flux;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Bancos')]
class Index extends Component
{
    use ConfirmsDeletion;

    public string $name = '';

    public string $code = '';

    public ?int $editingBankId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Bank::class);
    }

    #[Computed]
    public function banks(): Collection
    {
        return Auth::user()->banks()->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->authorize('create', Bank::class);

        $this->reset('name', 'code', 'editingBankId');
        $this->resetValidation();

        Flux::modal('bank-form')->show();
    }

    public function edit(Bank $bank): void
    {
        $this->authorize('update', $bank);

        $this->editingBankId = $bank->id;
        $this->name = $bank->name;
        $this->code = (string) $bank->code;
        $this->resetValidation();

        Flux::modal('bank-form')->show();
    }

    public function save(): void
    {
        $user = Auth::user();
        $bank = null;

        if ($this->editingBankId !== null) {
            $bank = $user->banks()->findOrFail($this->editingBankId);
            $this->authorize('update', $bank);
        } else {
            $this->authorize('create', Bank::class);
        }

        $validated = $this->validate();

        if ($bank) {
            $bank->update($validated);
            Flux::toast(variant: 'success', text: __('Bank updated.'));
        } else {
            $user->banks()->create($validated);
            Flux::toast(variant: 'success', text: __('Bank created.'));
        }

        unset($this->banks);
        $this->reset('name', 'code', 'editingBankId');
        Flux::modal('bank-form')->close();
    }

    public function confirmDelete(Bank $bank): void
    {
        $this->confirmDeletionOf($bank);
    }

    protected function findForDeletion(int $id): Model
    {
        return Auth::user()->banks()->findOrFail($id);
    }

    protected function deletionModalName(): string
    {
        return 'confirm-bank-deletion';
    }

    protected function deletionSuccessMessage(): string
    {
        return __('Bank deleted.');
    }

    protected function afterDeletion(): void
    {
        unset($this->banks);
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function rules(): array
    {
        $unique = Rule::unique(Bank::class)->where(fn ($query) => $query->where('user_id', Auth::id()));

        if ($this->editingBankId !== null) {
            $unique = $unique->ignore($this->editingBankId);
        }

        return [
            'name' => ['required', 'string', 'max:100', $unique],
            'code' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function render()
    {
        return view('livewire.banks.index');
    }
}
