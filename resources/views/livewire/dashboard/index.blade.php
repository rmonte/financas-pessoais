<section class="w-full">
    <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
    <flux:subheading>{{ __('Your finances at a glance.') }}</flux:subheading>

    @php
        $resultPositive = (float) $this->monthSummary['balance'] >= 0;
        $balancePositive = (float) $this->totalBalance >= 0;
    @endphp

    <flux:subheading class="mt-6">{{ __('Wealth') }}</flux:subheading>

    <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('Accounts') }}</flux:text>
            <flux:heading size="xl" class="mt-1">
                {{ number_format((float) $this->accountsTotalValue, 2, ',', '.') }}
            </flux:heading>
        </flux:card>

        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('Investments') }}</flux:text>
            <flux:heading size="xl" class="mt-1">
                {{ number_format((float) $this->investmentsTotalValue, 2, ',', '.') }}
            </flux:heading>
        </flux:card>

        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('Total balance') }}</flux:text>
            <flux:heading size="xl" class="mt-1 flex items-center gap-1 {{ $balancePositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                @if ($balancePositive)
                    <flux:icon.arrow-trending-up variant="micro" class="size-5 shrink-0" />
                @else
                    <flux:icon.arrow-trending-down variant="micro" class="size-5 shrink-0" />
                @endif
                {{ number_format((float) $this->totalBalance, 2, ',', '.') }}
            </flux:heading>
        </flux:card>
    </div>

    <flux:subheading class="mt-6">{{ __('Current month') }}</flux:subheading>

    <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('Income this month') }}</flux:text>
            <flux:heading size="xl" class="mt-1 text-green-600 dark:text-green-400">
                {{ number_format((float) $this->monthSummary['income'], 2, ',', '.') }}
            </flux:heading>
        </flux:card>

        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('Expenses this month') }}</flux:text>
            <flux:heading size="xl" class="mt-1 text-red-600 dark:text-red-400">
                {{ number_format((float) $this->monthSummary['expense'], 2, ',', '.') }}
            </flux:heading>
        </flux:card>

        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('Result this month') }}</flux:text>
            <flux:heading size="xl" class="mt-1 flex items-center gap-1 {{ $resultPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                @if ($resultPositive)
                    <flux:icon.arrow-trending-up variant="micro" class="size-5 shrink-0" />
                @else
                    <flux:icon.arrow-trending-down variant="micro" class="size-5 shrink-0" />
                @endif
                {{ number_format((float) $this->monthSummary['balance'], 2, ',', '.') }}
            </flux:heading>
        </flux:card>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <flux:card size="sm" class="lg:col-span-2">
            <flux:heading size="lg">{{ __('Patrimony evolution') }}</flux:heading>
            <flux:subheading>{{ __('Accounts and investments, year by year') }}</flux:subheading>

            <flux:chart :value="$this->patrimonyEvolution" class="mt-4 h-64">
                <flux:chart.svg>
                    <flux:chart.area field="balance" class="text-blue-500/10" />
                    <flux:chart.line field="balance" class="text-blue-500" />
                    <flux:chart.axis axis="x" field="year">
                        <flux:chart.axis.line />
                        <flux:chart.axis.tick />
                    </flux:chart.axis>
                    <flux:chart.axis axis="y" :format="['useGrouping' => true]">
                        <flux:chart.axis.grid />
                        <flux:chart.axis.tick />
                    </flux:chart.axis>
                    <flux:chart.cursor />
                </flux:chart.svg>
                <flux:chart.tooltip>
                    <flux:chart.tooltip.heading field="year" />
                    <flux:chart.tooltip.value field="balance" label="{{ __('Balance') }}" :format="['useGrouping' => true, 'minimumFractionDigits' => 2]" />
                </flux:chart.tooltip>
            </flux:chart>
        </flux:card>

        <flux:card size="sm">
            <flux:heading size="lg">{{ __('Expenses by category') }}</flux:heading>
            <flux:subheading>{{ __('Current month') }}</flux:subheading>

            @if (count($this->expenseByCategory) > 0)
                <div class="mt-4 flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                    <flux:chart :value="$this->expenseByCategory">
                        <flux:chart.viewport class="mx-auto h-48 w-48 shrink-0">
                            <flux:chart.svg>
                                <flux:chart.pie field="value" label-field="label" inner-radius="60%" radius="4" class="dark:stroke-zinc-800" />
                            </flux:chart.svg>
                        </flux:chart.viewport>
                        <flux:chart.tooltip>
                            <flux:chart.tooltip.value label-field="label" field="value" :format="['useGrouping' => true, 'minimumFractionDigits' => 2]">
                                <flux:chart.tooltip.indicator />
                            </flux:chart.tooltip.value>
                        </flux:chart.tooltip>
                    </flux:chart>

                    <div class="flex flex-col items-start gap-2">
                        @foreach ($this->expenseByCategory as $category)
                            <flux:badge size="sm" :color="$category['color']">
                                {{ $category['label'] }} · {{ number_format($category['value'], 2, ',', '.') }}
                            </flux:badge>
                        @endforeach
                    </div>
                </div>
            @else
                <flux:text class="mt-4">{{ __('No expenses recorded this month.') }}</flux:text>
            @endif
        </flux:card>
    </div>

    <flux:card size="sm" class="mt-6">
        <flux:heading size="lg">{{ __('Income vs. expenses') }}</flux:heading>
        <flux:subheading>{{ __('Last 6 months') }}</flux:subheading>

        <flux:chart :value="$this->incomeExpenseByMonth" class="mt-4 h-64">
            <flux:chart.svg>
                <flux:chart.group>
                    <flux:chart.bar field="income" class="text-green-500" radius="4" />
                    <flux:chart.bar field="expense" class="text-red-500" radius="4" />
                </flux:chart.group>
                <flux:chart.axis axis="x" field="month">
                    <flux:chart.axis.line />
                    <flux:chart.axis.tick />
                </flux:chart.axis>
                <flux:chart.axis axis="y" :format="['useGrouping' => true]">
                    <flux:chart.axis.grid />
                    <flux:chart.axis.tick />
                </flux:chart.axis>
                <flux:chart.cursor type="area" />
            </flux:chart.svg>
            <flux:chart.tooltip>
                <flux:chart.tooltip.heading field="month" />
                <flux:chart.tooltip.value field="income" label="{{ __('Income') }}" :format="['useGrouping' => true, 'minimumFractionDigits' => 2]" />
                <flux:chart.tooltip.value field="expense" label="{{ __('Expenses') }}" :format="['useGrouping' => true, 'minimumFractionDigits' => 2]" />
            </flux:chart.tooltip>
        </flux:chart>

        <div class="mt-4 flex justify-center gap-4">
            <flux:chart.legend label="{{ __('Income') }}">
                <flux:chart.legend.indicator class="bg-green-500" />
            </flux:chart.legend>
            <flux:chart.legend label="{{ __('Expenses') }}">
                <flux:chart.legend.indicator class="bg-red-500" />
            </flux:chart.legend>
        </div>
    </flux:card>

    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <flux:card size="sm">
            <flux:heading size="lg">{{ __('Accounts') }}</flux:heading>

            <div class="mt-4 overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Account') }}</flux:table.column>
                        <flux:table.column>{{ __('Balance') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->accounts as $account)
                            <flux:table.row wire:key="dashboard-account-{{ $account->id }}">
                                <flux:table.cell>
                                    {{ $account->name }}
                                    @if ($account->is_credit_card)
                                        <flux:badge size="sm" color="purple">{{ __('Credit card') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:text class="{{ (float) $account->currentBalance() < 0 ? 'text-red-600 dark:text-red-400' : '' }}">
                                        {{ $account->currency->label() }} {{ number_format((float) $account->currentBalance(), 2, ',', '.') }}
                                    </flux:text>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="2">
                                    <flux:text>{{ __('No active accounts yet.') }}</flux:text>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>

        <flux:card size="sm">
            <flux:heading size="lg">{{ __('Investments') }}</flux:heading>

            <div class="mt-4 overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Investment') }}</flux:table.column>
                        <flux:table.column>{{ __('Value (BRL)') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->investments as $investment)
                            @php
                                $isUsd = $investment->currency === \App\Enums\Currency::USD;
                                $valueInBrl = (float) $investment->currentValue() * ($isUsd ? $this->exchangeRate : 1);
                            @endphp
                            <flux:table.row wire:key="dashboard-investment-{{ $investment->id }}">
                                <flux:table.cell>
                                    <flux:link :href="route('investments.show', $investment)" wire:navigate>
                                        {{ $investment->name }}
                                    </flux:link>
                                </flux:table.cell>
                                <flux:table.cell>{{ number_format($valueInBrl, 2, ',', '.') }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="2">
                                    <flux:text>{{ __('No investments registered yet.') }}</flux:text>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>

        <flux:card size="sm">
            <flux:heading size="lg">{{ __('Upcoming invoices') }}</flux:heading>

            <div class="mt-4 overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Account') }}</flux:table.column>
                        <flux:table.column>{{ __('Due date') }}</flux:table.column>
                        <flux:table.column>{{ __('Amount') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->upcomingInvoices as $invoice)
                            <flux:table.row wire:key="dashboard-invoice-{{ $invoice->id }}">
                                <flux:table.cell>{{ $invoice->account->name }}</flux:table.cell>
                                <flux:table.cell>{{ $invoice->due_date->translatedFormat('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell>{{ number_format((float) $invoice->total(), 2, ',', '.') }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" :color="$invoice->status()->color()">
                                        {{ $invoice->status()->label() }}
                                    </flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4">
                                    <flux:text>{{ __('No pending invoices.') }}</flux:text>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>

        <flux:card size="sm">
            <flux:heading size="lg">{{ __('Upcoming recurring transactions') }}</flux:heading>

            <div class="mt-4 overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Description') }}</flux:table.column>
                        <flux:table.column>{{ __('Next date') }}</flux:table.column>
                        <flux:table.column>{{ __('Amount') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->upcomingRecurringTransactions as $recurringTransaction)
                            <flux:table.row wire:key="dashboard-recurring-{{ $recurringTransaction->id }}">
                                <flux:table.cell>
                                    {{ $recurringTransaction->description ?? $recurringTransaction->category?->name ?? __('Recurring transaction') }}
                                    <flux:badge size="sm" :color="$recurringTransaction->type->color()">
                                        {{ $recurringTransaction->type->label() }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ $recurringTransaction->next_occurrence->translatedFormat('d/m/Y') }}</flux:table.cell>
                                <flux:table.cell>{{ number_format((float) $recurringTransaction->amount, 2, ',', '.') }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="3">
                                    <flux:text>{{ __('No active recurring transactions.') }}</flux:text>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>
    </div>
</section>
