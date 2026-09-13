<?php

namespace App\Livewire\Invoices;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Transaction;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read Collection<int, Transaction> $transactions
 * @property-read Collection<int, Account> $paymentAccounts
 */
#[Title('Fatura')]
class Show extends Component
{
    public Invoice $invoice;

    public ?int $paymentAccountId = null;

    public function mount(Invoice $invoice): void
    {
        $this->authorize('view', $invoice);

        $this->invoice = $invoice;
    }

    /**
     * @return Collection<int, Transaction>
     */
    #[Computed]
    public function transactions(): Collection
    {
        return $this->invoice->transactions()->with('category')->orderByDesc('date')->get();
    }

    /**
     * @return Collection<int, Account>
     */
    #[Computed]
    public function paymentAccounts(): Collection
    {
        return Auth::user()->accounts()->where('is_credit_card', false)->orderBy('name')->get();
    }

    public function confirmPayment(): void
    {
        $this->authorize('update', $this->invoice);

        Flux::modal('confirm-invoice-payment')->show();
    }

    public function pay(): void
    {
        $this->authorize('update', $this->invoice);

        $validated = $this->validate([
            'paymentAccountId' => [
                'required',
                'integer',
                Rule::exists(Account::class, 'id')->where(fn ($query) => $query->where('user_id', Auth::id())->where('is_credit_card', false)),
            ],
        ]);

        $transaction = Auth::user()->transactions()->create([
            'account_id' => $validated['paymentAccountId'],
            'to_account_id' => $this->invoice->account_id,
            'category_id' => null,
            'type' => TransactionType::Transfer,
            'description' => __('Invoice payment :month/:year', ['month' => $this->invoice->reference_month, 'year' => $this->invoice->reference_year]),
            'date' => now()->toDateString(),
            'amount' => $this->invoice->total(),
        ]);

        $this->invoice->update(['payment_transaction_id' => $transaction->id]);

        $this->reset('paymentAccountId');
        Flux::modal('confirm-invoice-payment')->close();
        Flux::toast(variant: 'success', text: __('Invoice marked as paid.'));
    }

    public function render(): View
    {
        return view('livewire.invoices.show');
    }
}
