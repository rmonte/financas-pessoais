<section class="w-full">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Transactions') }}</flux:heading>
            <flux:subheading>{{ __('Track the money moving in and out of your accounts.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create">
            {{ __('New transaction') }}
        </flux:button>
    </div>

    @php $resultPositive = (float) $this->totals['balance'] >= 0; @endphp

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('Income') }}</flux:text>
            <flux:heading size="xl" class="mt-1 text-green-600 dark:text-green-400">
                {{ number_format((float) $this->totals['income'], 2, ',', '.') }}
            </flux:heading>
        </flux:card>

        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('Expenses') }}</flux:text>
            <flux:heading size="xl" class="mt-1 text-red-600 dark:text-red-400">
                {{ number_format((float) $this->totals['expense'], 2, ',', '.') }}
            </flux:heading>
        </flux:card>

        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('Transfers') }}</flux:text>
            <flux:heading size="xl" class="mt-1 text-blue-600 dark:text-blue-400">
                {{ number_format((float) $this->totals['transfer'], 2, ',', '.') }}
            </flux:heading>
        </flux:card>

        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('Invested') }}</flux:text>
            <flux:heading size="xl" class="mt-1 text-violet-600 dark:text-violet-400">
                {{ number_format((float) $this->totals['investment'], 2, ',', '.') }}
            </flux:heading>
        </flux:card>

        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('Result') }}</flux:text>
            <flux:heading size="xl" class="mt-1 flex items-center gap-1 {{ $resultPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                @if ($resultPositive)
                    <flux:icon.arrow-trending-up variant="micro" class="size-5 shrink-0" />
                @else
                    <flux:icon.arrow-trending-down variant="micro" class="size-5 shrink-0" />
                @endif
                {{ number_format((float) $this->totals['balance'], 2, ',', '.') }}
            </flux:heading>
        </flux:card>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <flux:select wire:model.live="filterType" :label="__('Type')">
            <flux:select.option value="">{{ __('All') }}</flux:select.option>
            @foreach (\App\Enums\TransactionType::cases() as $transactionType)
                <flux:select.option value="{{ $transactionType->value }}">{{ $transactionType->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterAccountId" :label="__('Account')">
            <flux:select.option value="">{{ __('All') }}</flux:select.option>
            @foreach ($this->accounts as $account)
                <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterCategoryId" :label="__('Category')">
            <flux:select.option value="">{{ __('All') }}</flux:select.option>
            @foreach ($this->allCategories as $category)
                <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterMonth" :label="__('Month')">
            @foreach ($this->months as $value => $label)
                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex items-end gap-2">
            <flux:select wire:model.live="filterYear" :label="__('Year')" class="grow">
                @foreach ($this->years as $year)
                    <flux:select.option value="{{ $year }}">{{ $year }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:button variant="filled" wire:click="resetFilters">
                {{ __('Clear') }}
            </flux:button>
        </div>
    </div>

    <div class="mt-6 overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Account') }}</flux:table.column>
                <flux:table.column>{{ __('Category') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->transactions as $transaction)
                    <flux:table.row wire:key="transaction-{{ $transaction->id }}">
                        <flux:table.cell>{{ $transaction->date->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$transaction->type->color()">
                                {{ $transaction->type->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($transaction->type === \App\Enums\TransactionType::Transfer)
                                {{ $transaction->account->name }} &rarr; {{ $transaction->toAccount?->name }}
                            @else
                                {{ $transaction->account->name }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $transaction->category?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $transaction->description ?? '—' }}
                            @if ($transaction->installment_total)
                                <flux:badge size="sm" color="zinc">{{ $transaction->installment_number }}/{{ $transaction->installment_total }}</flux:badge>
                            @endif
                            @if ($transaction->recurring_transaction_id)
                                <flux:badge size="sm" color="purple" icon="arrow-path">{{ __('Recurring') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $sign = match (true) {
                                    $transaction->type === \App\Enums\TransactionType::Expense => '-',
                                    $transaction->type === \App\Enums\TransactionType::Income => '+',
                                    $transaction->type === \App\Enums\TransactionType::Investment => $transaction->investmentOperation->type->isOutgoing() ? '-' : '+',
                                    default => '',
                                };
                            @endphp
                            {{ $sign }} {{ number_format((float) $transaction->amount, 2, ',', '.') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />

                                <flux:menu>
                                    @if ($transaction->investment_operation_id !== null)
                                        <flux:menu.item icon="eye" :href="route('investments.show', $transaction->investmentOperation->investment)" wire:navigate>
                                            {{ __('View investment') }}
                                        </flux:menu.item>
                                    @else
                                        <flux:menu.item icon="pencil" wire:click="edit({{ $transaction->id }})">
                                            {{ __('Edit') }}
                                        </flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $transaction->id }})">
                                            {{ __('Delete') }}
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7">
                            <flux:text>{{ __('No transactions registered yet.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="transaction-form" :show="$errors->isNotEmpty()" focusable class="max-w-md">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingTransactionId ? __('Edit transaction') : __('New transaction') }}
            </flux:heading>

            <flux:select wire:model.live="type" :label="__('Type')">
                @foreach ($this->manualTransactionTypes as $transactionType)
                    <flux:select.option value="{{ $transactionType->value }}">{{ $transactionType->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="account_id" :label="__('Account')">
                <flux:select.option value="">{{ __('Select an account') }}</flux:select.option>
                @foreach ($this->accounts as $account)
                    <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($type === \App\Enums\TransactionType::Transfer->value)
                <flux:select wire:model="to_account_id" :label="__('To account')">
                    <flux:select.option value="">{{ __('Select an account') }}</flux:select.option>
                    @foreach ($this->accounts as $account)
                        <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @else
                <flux:select wire:model="category_id" :label="__('Category')">
                    <flux:select.option value="">{{ __('Select a category') }}</flux:select.option>
                    @foreach ($this->categories as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input wire:model="description" :label="__('Description')" />

            <flux:input wire:model="date" :label="__('Date')" type="date" />

            <flux:input wire:model="amount" :label="__('Amount')" type="number" step="0.01" />

            @if (! $editingTransactionId && $type === \App\Enums\TransactionType::Expense->value)
                <flux:input wire:model="installments" :label="__('Installments')" :description="__('Total amount will be split evenly across this many months, starting on the date above.')" type="number" min="1" max="60" />
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-transaction-deletion" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Delete transaction?') }}</flux:heading>
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
