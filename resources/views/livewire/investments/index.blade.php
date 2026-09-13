<section class="w-full">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Investments') }}</flux:heading>
            <flux:subheading>{{ __('Track your stocks, treasury bonds, pension, and fixed income.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="createOperation">
            {{ __('New operation') }}
        </flux:button>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('Total value') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ number_format((float) $this->totalValueInBrl, 2, ',', '.') }}</flux:heading>
        </flux:card>

        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('USD exchange rate') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ number_format($this->exchangeRate, 4, ',', '.') }}</flux:heading>
        </flux:card>

        @foreach ($this->allocationByAssetClass as $class)
            <flux:card variant="soft" size="sm">
                <flux:text>{{ $class['label'] }}</flux:text>
                <flux:heading size="xl" class="mt-1">{{ number_format($class['value'], 2, ',', '.') }}</flux:heading>
            </flux:card>
        @endforeach
    </div>

    <flux:tabs wire:model.live="statusFilter" class="mt-6">
        <flux:tab name="active">{{ __('Active investments') }}</flux:tab>
        <flux:tab name="inactive">{{ __('Closed investments') }}</flux:tab>
    </flux:tabs>

    <div class="mt-6 overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Bank') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Quantity') }}</flux:table.column>
                <flux:table.column>{{ __('Avg. price') }}</flux:table.column>
                <flux:table.column>{{ __('Current price') }}</flux:table.column>
                <flux:table.column>{{ __('Price change') }}</flux:table.column>
                <flux:table.column>{{ __('Dividends / JCP') }}</flux:table.column>
                <flux:table.column>{{ __('Total gain') }}</flux:table.column>
                <flux:table.column>{{ __('Current value (BRL)') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->filteredInvestments as $investment)
                    @php
                        $isQuantityBased = $investment->type->isQuantityBased();
                        $nativeValue = (float) $investment->currentValue();
                        $isUsd = $investment->currency === \App\Enums\Currency::USD;
                        $valueInBrl = $isUsd ? $nativeValue * $this->exchangeRate : $nativeValue;
                        $priceChange = $investment->priceChangePercentage();
                        $totalGain = (float) $investment->totalGain();
                    @endphp
                    <flux:table.row wire:key="investment-{{ $investment->id }}">
                        <flux:table.cell>
                            {{ $investment->name }}
                            @if ($investment->ticker)
                                <flux:badge size="sm" color="zinc">{{ $investment->ticker }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $investment->bank?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$investment->type->color()">
                                {{ $investment->type->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $isQuantityBased ? number_format((float) $investment->currentQuantity(), 4, ',', '.') : '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $isQuantityBased ? number_format((float) $investment->averagePrice(), 2, ',', '.') : '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $isQuantityBased && $investment->current_price !== null ? number_format((float) $investment->current_price, 2, ',', '.') : '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($priceChange !== null)
                                <flux:text class="{{ (float) $priceChange < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                    {{ (float) $priceChange > 0 ? '+' : '' }}{{ number_format((float) $priceChange, 2, ',', '.') }}%
                                </flux:text>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="text-green-600 dark:text-green-400">
                                {{ number_format((float) $investment->dividendsReceived(), 2, ',', '.') }}
                            </flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:text class="{{ $totalGain < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                {{ number_format($totalGain, 2, ',', '.') }}
                            </flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ number_format($valueInBrl, 2, ',', '.') }}
                            @if ($isUsd)
                                <flux:text size="sm" class="text-zinc-500">
                                    (US$ {{ number_format($nativeValue, 2, ',', '.') }})
                                </flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />

                                <flux:menu>
                                    <flux:menu.item icon="eye" :href="route('investments.show', $investment)" wire:navigate>
                                        {{ __('View') }}
                                    </flux:menu.item>
                                    <flux:menu.item icon="pencil" wire:click="edit({{ $investment->id }})">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $investment->id }})">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="11">
                            <flux:text>
                                {{ $statusFilter === 'active' ? __('No active investments.') : __('No closed investments.') }}
                            </flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="investment-form" :show="$errors->isNotEmpty()" focusable class="max-w-md">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ __('Edit investment') }}</flux:heading>

            <flux:select wire:model.live="type" :label="__('Type')">
                @foreach (\App\Enums\InvestmentType::cases() as $typeOption)
                    <flux:select.option value="{{ $typeOption->value }}">{{ $typeOption->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="name" :label="__('Name')" autofocus />

            <flux:select wire:model="bank_id" :label="__('Bank / broker')">
                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                @foreach ($this->banks as $bank)
                    <flux:select.option value="{{ $bank->id }}">{{ $bank->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="currency" :label="__('Currency')">
                @foreach (\App\Enums\Currency::cases() as $currencyOption)
                    <flux:select.option value="{{ $currencyOption->value }}">{{ $currencyOption->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            @if (\App\Enums\InvestmentType::tryFrom($type)?->isQuantityBased())
                <flux:input wire:model="ticker" :label="__('Ticker / code')" placeholder="AAPL, Tesouro IPCA+ 2035, PGBL Icatu..." />

                <flux:input wire:model="current_price" :label="__('Current price / quota')" type="number" step="0.0001" />
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="operation-form" :show="$errors->isNotEmpty()" focusable class="max-w-md">
        <form wire:submit="saveOperation" class="space-y-6">
            <flux:heading size="lg">{{ __('New operation') }}</flux:heading>

            <flux:select wire:model.live="operationInvestmentId" :label="__('Investment')">
                <flux:select.option value="">{{ __('Select an investment') }}</flux:select.option>
                @foreach ($this->investments as $investmentOption)
                    <flux:select.option value="{{ $investmentOption->id }}">{{ $investmentOption->name }}</flux:select.option>
                @endforeach
                <flux:select.option value="__new__">{{ __('+ New investment') }}</flux:select.option>
            </flux:select>

            @if ($operationInvestmentId === '__new__')
                <flux:select wire:model.live="type" :label="__('Type')">
                    @foreach (\App\Enums\InvestmentType::cases() as $typeOption)
                        <flux:select.option value="{{ $typeOption->value }}">{{ $typeOption->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="name" :label="__('Name')" autofocus />

                <flux:select wire:model="bank_id" :label="__('Bank / broker')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($this->banks as $bank)
                        <flux:select.option value="{{ $bank->id }}">{{ $bank->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="currency" :label="__('Currency')">
                    @foreach (\App\Enums\Currency::cases() as $currencyOption)
                        <flux:select.option value="{{ $currencyOption->value }}">{{ $currencyOption->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if (\App\Enums\InvestmentType::tryFrom($type)?->isQuantityBased())
                    <flux:input wire:model="ticker" :label="__('Ticker / code')" placeholder="AAPL, Tesouro IPCA+ 2035, PGBL Icatu..." />
                @endif
            @endif

            @if ($operationInvestmentId !== '')
                <flux:select wire:model.live="operationType" :label="__('Operation type')">
                    @foreach ($this->operationTypeOptions as $operationTypeOption)
                        <flux:select.option value="{{ $operationTypeOption->value }}">{{ $operationTypeOption->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="operationDate" :label="__('Date')" type="date" />

                @if (\App\Enums\InvestmentOperationType::tryFrom($operationType)?->isTrade())
                    <flux:input wire:model.live="operationQuantity" :label="__('Quantity')" type="number" step="0.000001" />

                    <flux:input wire:model.live="operationUnitPrice" :label="__('Unit price')" type="number" step="0.0001" />
                @endif

                <flux:input wire:model="operationAmount" :label="__('Total amount')" type="number" step="0.01" />

                <flux:field>
                    <flux:select wire:model="operationAccountId" :label="__('Account')">
                        <flux:select.option value="">{{ __('None (internal move)') }}</flux:select.option>
                        @foreach ($this->accounts as $account)
                            <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>
                        {{ __('Choose an account to automatically record this as a transaction there. Leave blank if you already held this position before using the app.') }}
                    </flux:description>
                </flux:field>

                <flux:input wire:model="operationDescription" :label="__('Description')" />
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-investment-deletion" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Delete investment?') }}</flux:heading>
            <flux:subheading>{{ __('This also deletes its full operation history. This action cannot be undone.') }}</flux:subheading>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
