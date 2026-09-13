<section class="w-full">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">
                {{ $investment->name }}
                @if ($investment->ticker)
                    <flux:badge size="sm" color="zinc">{{ $investment->ticker }}</flux:badge>
                @endif
            </flux:heading>
            <flux:subheading>{{ $investment->bank?->name ?? __('No bank') }}</flux:subheading>
        </div>

        <flux:badge :color="$investment->type->color()">
            {{ $investment->type->label() }}
        </flux:badge>
    </div>

    @php
        $isQuantityBased = $investment->type->isQuantityBased();
        $nativeValue = (float) $investment->currentValue();
        $isUsd = $investment->currency === \App\Enums\Currency::USD;
        $valueInBrl = $isUsd ? $nativeValue * $this->exchangeRate : $nativeValue;
        $priceChange = $investment->priceChangePercentage();
        $totalGain = (float) $investment->totalGain();
        $priceChangePositive = $priceChange !== null && (float) $priceChange >= 0;
        $totalGainPositive = $totalGain >= 0;
    @endphp

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <flux:card variant="soft" size="sm">
            <flux:text>
                {{ $isQuantityBased ? __('Quantity held') : __('Current balance') }}
            </flux:text>
            <flux:heading size="xl" class="mt-1">
                @if ($isQuantityBased)
                    {{ number_format((float) $investment->currentQuantity(), 4, ',', '.') }}
                @else
                    {{ $investment->currency->label() }} {{ number_format((float) $investment->currentBalance(), 2, ',', '.') }}
                @endif
            </flux:heading>
        </flux:card>

        @if ($isQuantityBased)
            <flux:card variant="soft" size="sm">
                <flux:text>{{ __('Average price') }}</flux:text>
                <flux:heading size="xl" class="mt-1">{{ number_format((float) $investment->averagePrice(), 2, ',', '.') }}</flux:heading>
            </flux:card>

            <flux:card variant="soft" size="sm">
                <flux:text>{{ __('Current price') }}</flux:text>
                <flux:heading size="xl" class="mt-1">
                    {{ $investment->current_price !== null ? number_format((float) $investment->current_price, 2, ',', '.') : '—' }}
                </flux:heading>
                @if ($priceChange !== null)
                    <flux:text class="mt-1 flex items-center gap-1 {{ $priceChangePositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        @if ($priceChangePositive)
                            <flux:icon.arrow-trending-up variant="micro" class="size-4 shrink-0" />
                        @else
                            <flux:icon.arrow-trending-down variant="micro" class="size-4 shrink-0" />
                        @endif
                        {{ (float) $priceChange > 0 ? '+' : '' }}{{ number_format((float) $priceChange, 2, ',', '.') }}%
                    </flux:text>
                @endif
            </flux:card>
        @endif

        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('Current value (BRL)') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ number_format($valueInBrl, 2, ',', '.') }}</flux:heading>
            @if ($isUsd)
                <flux:text size="sm" class="text-zinc-500">US$ {{ number_format($nativeValue, 2, ',', '.') }}</flux:text>
            @endif
        </flux:card>

        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('Dividends / JCP received') }}</flux:text>
            <flux:heading size="xl" class="mt-1 text-green-600 dark:text-green-400">
                {{ $investment->currency->label() }} {{ number_format((float) $investment->dividendsReceived(), 2, ',', '.') }}
            </flux:heading>
        </flux:card>

        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('Total gain') }}</flux:text>
            <flux:heading size="xl" class="mt-1 flex items-center gap-1 {{ $totalGainPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                @if ($totalGainPositive)
                    <flux:icon.arrow-trending-up variant="micro" class="size-5 shrink-0" />
                @else
                    <flux:icon.arrow-trending-down variant="micro" class="size-5 shrink-0" />
                @endif
                {{ $investment->currency->label() }} {{ number_format($totalGain, 2, ',', '.') }}
            </flux:heading>
        </flux:card>
    </div>

    <div class="mt-6 flex items-center justify-between">
        <flux:heading size="lg">{{ __('Operations') }}</flux:heading>

        <flux:button variant="primary" icon="plus" wire:click="create">
            {{ __('New operation') }}
        </flux:button>
    </div>

    <flux:table class="mt-4">
        <flux:table.columns>
            <flux:table.column>{{ __('Date') }}</flux:table.column>
            <flux:table.column>{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('Quantity') }}</flux:table.column>
            <flux:table.column>{{ __('Unit price') }}</flux:table.column>
            <flux:table.column>{{ __('Amount') }}</flux:table.column>
            <flux:table.column>{{ __('Account') }}</flux:table.column>
            <flux:table.column>{{ __('Description') }}</flux:table.column>
            <flux:table.column>{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->operations as $operation)
                <flux:table.row wire:key="operation-{{ $operation->id }}">
                    <flux:table.cell>{{ $operation->date->format('d/m/Y') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$operation->type->color()">
                            {{ $operation->type->label() }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $operation->quantity ? number_format((float) $operation->quantity, 4, ',', '.') : '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $operation->unit_price ? number_format((float) $operation->unit_price, 4, ',', '.') : '—' }}</flux:table.cell>
                    <flux:table.cell>{{ number_format((float) $operation->amount, 2, ',', '.') }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($operation->account)
                            {{ $operation->account->name }}
                        @else
                            <flux:text size="sm" class="text-zinc-500">{{ __('Internal') }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $operation->description ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />

                            <flux:menu>
                                <flux:menu.item icon="pencil" wire:click="edit({{ $operation->id }})">
                                    {{ __('Edit') }}
                                </flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $operation->id }})">
                                    {{ __('Delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8">
                        <flux:text>{{ __('No operations registered yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="operation-form" :show="$errors->isNotEmpty()" focusable class="max-w-md">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingOperationId ? __('Edit operation') : __('New operation') }}
            </flux:heading>

            <flux:select wire:model.live="type" :label="__('Type')">
                @foreach ($this->operationTypes as $operationType)
                    <flux:select.option value="{{ $operationType->value }}">{{ $operationType->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="date" :label="__('Date')" type="date" />

            @if (\App\Enums\InvestmentOperationType::tryFrom($type)?->isTrade())
                <flux:input wire:model.live="quantity" :label="__('Quantity')" type="number" step="0.000001" />

                <flux:input wire:model.live="unit_price" :label="__('Unit price')" type="number" step="0.0001" />
            @endif

            <flux:input wire:model="amount" :label="__('Total amount')" type="number" step="0.01" />

            <flux:field>
                <flux:select wire:model="account_id" :label="__('Account')">
                    <flux:select.option value="">{{ __('None (internal move)') }}</flux:select.option>
                    @foreach ($this->accounts as $account)
                        <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:description>
                    {{ __('Choose an account to automatically record this as a transaction there. Leave blank if the money is already inside this broker.') }}
                </flux:description>
            </flux:field>

            <flux:input wire:model="description" :label="__('Description')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-operation-deletion" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Delete operation?') }}</flux:heading>
            <flux:subheading>{{ __('This action cannot be undone.') }}</flux:subheading>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
