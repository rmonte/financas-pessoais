<?php

namespace App\Livewire\Invoices;

use App\Models\Invoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Faturas')]
class Index extends Component
{
    public ?int $filterAccountId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Invoice::class);
    }

    #[Computed]
    public function invoices(): Collection
    {
        return Auth::user()->invoices()
            ->with(['account', 'transactions'])
            ->when($this->filterAccountId !== null, fn ($query) => $query->where('account_id', $this->filterAccountId))
            ->orderByDesc('due_date')
            ->get();
    }

    #[Computed]
    public function creditCardAccounts(): Collection
    {
        return Auth::user()->accounts()->where('is_credit_card', true)->orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.invoices.index');
    }
}
