<section class="w-full">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Accounts') }}</flux:heading>
            <flux:subheading>{{ __('Manage the accounts you use to track your finances.') }}</flux:subheading>
        </div>

        <div class="flex gap-2">
            <flux:button variant="ghost" icon="banknotes" :href="route('banks.index')" wire:navigate>
                {{ __('Manage banks') }}
            </flux:button>
            <flux:button variant="primary" icon="plus" wire:click="create">
                {{ __('New account') }}
            </flux:button>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('Total balance') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ number_format((float) $this->totalBalanceInBrl, 2, ',', '.') }}</flux:heading>
        </flux:card>

        <flux:card variant="soft" size="sm">
            <flux:text>{{ __('Active accounts') }}</flux:text>
            <flux:heading size="xl" class="mt-1">{{ $this->activeAccountsCount }}</flux:heading>
        </flux:card>
    </div>

    <div class="mt-6 overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Bank') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Initial balance') }}</flux:table.column>
                <flux:table.column>{{ __('Active') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->accounts as $account)
                    <flux:table.row wire:key="account-{{ $account->id }}">
                        <flux:table.cell>{{ $account->name }}</flux:table.cell>
                        <flux:table.cell>{{ $account->bank?->name ?? __('Cash') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($account->is_credit_card)
                                <flux:badge color="purple">{{ __('Credit card') }}</flux:badge>
                            @else
                                <flux:text>{{ __('Regular') }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $account->currency->label() }} {{ number_format((float) $account->initial_balance, 2, ',', '.') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$account->is_active ? 'green' : 'zinc'">
                                {{ $account->is_active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />

                                <flux:menu>
                                    <flux:menu.item icon="pencil" wire:click="edit({{ $account->id }})">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $account->id }})">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <flux:text>{{ __('No accounts registered yet.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="account-form" :show="$errors->isNotEmpty()" focusable class="max-w-md">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingAccountId ? __('Edit account') : __('New account') }}
            </flux:heading>

            <flux:input wire:model="name" :label="__('Name')" autofocus />

            @if (! $creatingBank)
                <div>
                    <flux:select wire:model="bank_id" :label="__('Bank')">
                        <flux:select.option value="">{{ __('None (cash)') }}</flux:select.option>
                        @foreach ($this->banks as $bank)
                            <flux:select.option value="{{ $bank->id }}">{{ $bank->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:button size="sm" variant="ghost" icon="plus" wire:click="startCreatingBank" class="mt-1">
                        {{ __('New bank') }}
                    </flux:button>
                </div>
            @else
                <div class="flex items-end gap-2">
                    <flux:input wire:model="newBankName" :label="__('New bank')" class="flex-1" autofocus />
                    <flux:button size="sm" variant="primary" wire:click="saveBank">{{ __('Save') }}</flux:button>
                    <flux:button size="sm" variant="filled" wire:click="cancelCreatingBank">{{ __('Cancel') }}</flux:button>
                </div>
            @endif

            <flux:select wire:model="currency" :label="__('Currency')">
                @foreach (\App\Enums\Currency::cases() as $currencyOption)
                    <flux:select.option value="{{ $currencyOption->value }}">{{ $currencyOption->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="description" :label="__('Description')" rows="3" />

            <flux:input wire:model="initial_balance" :label="__('Initial balance')" type="number" step="0.01" />

            <flux:checkbox wire:model="is_active" :label="__('Active')" />

            <flux:checkbox wire:model.live="is_credit_card" :label="__('Credit card')" />

            @if ($is_credit_card)
                <flux:input wire:model="credit_limit" :label="__('Credit limit')" type="number" step="0.01" />

                <flux:input wire:model="closing_day" :label="__('Closing day')" type="number" min="1" max="31" />

                <flux:input wire:model="due_day" :label="__('Due day')" type="number" min="1" max="31" />
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-account-deletion" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Delete account?') }}</flux:heading>
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
