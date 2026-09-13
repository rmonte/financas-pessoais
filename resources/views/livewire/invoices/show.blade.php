<section class="w-full">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">
                {{ $invoice->account->name }} — {{ str_pad((string) $invoice->reference_month, 2, '0', STR_PAD_LEFT) }}/{{ $invoice->reference_year }}
            </flux:heading>
            <flux:subheading>
                {{ __('Closes on :closing, due on :due', ['closing' => $invoice->closing_date->format('d/m/Y'), 'due' => $invoice->due_date->format('d/m/Y')]) }}
            </flux:subheading>
        </div>

        <flux:badge :color="$invoice->status()->color()">
            {{ $invoice->status()->label() }}
        </flux:badge>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('Total') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ number_format((float) $invoice->total(), 2, ',', '.') }}</flux:heading>
        </flux:card>

        <div class="flex items-center justify-end">
            @if (! $invoice->isPaid())
                <flux:button variant="primary" wire:click="confirmPayment">
                    {{ __('Mark as paid') }}
                </flux:button>
            @else
                <flux:text>{{ __('Paid on :date', ['date' => $invoice->paymentTransaction->date->format('d/m/Y')]) }}</flux:text>
            @endif
        </div>
    </div>

    <div class="mt-6 overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column>{{ __('Category') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->transactions as $transaction)
                    <flux:table.row wire:key="invoice-transaction-{{ $transaction->id }}">
                        <flux:table.cell>{{ $transaction->date->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>{{ $transaction->description ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $transaction->category?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ number_format((float) $transaction->amount, 2, ',', '.') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            <flux:text>{{ __('No purchases in this invoice.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="confirm-invoice-payment" :show="$errors->isNotEmpty()" focusable class="max-w-md">
        <form wire:submit="pay" class="space-y-6">
            <flux:heading size="lg">{{ __('Mark invoice as paid') }}</flux:heading>
            <flux:subheading>{{ __('This creates a transfer from the selected account to the card.') }}</flux:subheading>

            <flux:select wire:model="paymentAccountId" :label="__('Pay from')">
                <flux:select.option value="">{{ __('Select an account') }}</flux:select.option>
                @foreach ($this->paymentAccounts as $account)
                    <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Confirm payment') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
