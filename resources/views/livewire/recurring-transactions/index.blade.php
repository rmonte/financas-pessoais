<section class="w-full">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Recurring Transactions') }}</flux:heading>
            <flux:subheading>{{ __('Bills, subscriptions, and income that repeat every month.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create">
            {{ __('New recurring transaction') }}
        </flux:button>
    </div>

    <div class="mt-6 overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Account') }}</flux:table.column>
                <flux:table.column>{{ __('Category') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Starts on') }}</flux:table.column>
                <flux:table.column>{{ __('Active') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->recurringTransactions as $recurringTransaction)
                    <flux:table.row wire:key="recurring-{{ $recurringTransaction->id }}">
                        <flux:table.cell>
                            <flux:badge :color="$recurringTransaction->type->color()">
                                {{ $recurringTransaction->type->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($recurringTransaction->type === \App\Enums\TransactionType::Transfer)
                                {{ $recurringTransaction->account->name }} &rarr; {{ $recurringTransaction->toAccount?->name }}
                            @else
                                {{ $recurringTransaction->account->name }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $recurringTransaction->category?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $recurringTransaction->description ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ number_format((float) $recurringTransaction->amount, 2, ',', '.') }}</flux:table.cell>
                        <flux:table.cell>{{ $recurringTransaction->start_date->format('d/m/Y') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$recurringTransaction->is_active ? 'green' : 'zinc'">
                                {{ $recurringTransaction->is_active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />

                                <flux:menu>
                                    <flux:menu.item icon="pencil" wire:click="edit({{ $recurringTransaction->id }})">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $recurringTransaction->id }})">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8">
                            <flux:text>{{ __('No recurring transactions registered yet.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="recurring-transaction-form" :show="$errors->isNotEmpty()" focusable class="max-w-md">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingRecurringTransactionId ? __('Edit recurring transaction') : __('New recurring transaction') }}
            </flux:heading>

            <flux:select wire:model.live="type" :label="__('Type')">
                @foreach (\App\Enums\TransactionType::manuallyCreatable() as $transactionType)
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

            <flux:input wire:model="amount" :label="__('Amount')" type="number" step="0.01" />

            <flux:input wire:model="start_date" :label="__('Starts on')" :description="__('The day of the month it repeats on. Changing this after occurrences exist only affects future ones.')" type="date" />

            <flux:checkbox wire:model="is_active" :label="__('Active')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-recurring-transaction-deletion" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Delete recurring transaction?') }}</flux:heading>
            <flux:subheading>{{ __('Already generated transactions will be kept. This only stops future ones.') }}</flux:subheading>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
