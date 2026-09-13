<section class="w-full">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Invoices') }}</flux:heading>
            <flux:subheading>{{ __('Track your credit card bills and their purchases.') }}</flux:subheading>
        </div>
    </div>

    <flux:select wire:model.live="filterAccountId" :label="__('Card')" class="mt-6 max-w-xs">
        <flux:select.option value="">{{ __('All') }}</flux:select.option>
        @foreach ($this->creditCardAccounts as $account)
            <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
        @endforeach
    </flux:select>

    <div class="mt-6 overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Card') }}</flux:table.column>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column>{{ __('Closing') }}</flux:table.column>
                <flux:table.column>{{ __('Due') }}</flux:table.column>
                <flux:table.column>{{ __('Total') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->invoices as $invoice)
                    <flux:table.row wire:key="invoice-{{ $invoice->id }}">
                        <flux:table.cell>{{ $invoice->account->name }}</flux:table.cell>
                        <flux:table.cell>{{ str_pad((string) $invoice->reference_month, 2, '0', STR_PAD_LEFT) }}/{{ $invoice->reference_year }}</flux:table.cell>
                        <flux:table.cell>{{ $invoice->closing_date->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>{{ $invoice->due_date->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>{{ number_format((float) $invoice->total(), 2, ',', '.') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$invoice->status()->color()">
                                {{ $invoice->status()->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="sm" variant="ghost" icon="eye" :tooltip="__('View')" :href="route('invoices.show', $invoice)" wire:navigate />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            <flux:text>{{ __('No invoices yet.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
