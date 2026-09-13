<?php

namespace App\Livewire\Invoices;

use App\Models\Account;
use App\Models\Invoice;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, Account> $creditCardAccounts
 */
#[Title('Faturas')]
class Index extends Component
{
    public ?int $filterAccountId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Invoice::class);
    }

    /**
     * @return Collection<int, Invoice>
     */
    #[Computed]
    public function invoices(): Collection
    {
        return Auth::user()->invoices()
            ->with(['account', 'transactions'])
            ->when($this->filterAccountId !== null, fn ($query) => $query->where('account_id', $this->filterAccountId))
            ->orderByDesc('due_date')
            ->get();
    }

    /**
     * @return Collection<int, Account>
     */
    #[Computed]
    public function creditCardAccounts(): Collection
    {
        return Auth::user()->accounts()->where('is_credit_card', true)->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.invoices.index');
    }
}
