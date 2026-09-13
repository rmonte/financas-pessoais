<section class="w-full">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Banks') }}</flux:heading>
            <flux:subheading>{{ __('Manage the banks used across your accounts.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create">
            {{ __('New bank') }}
        </flux:button>
    </div>

    <div class="mt-6 overflow-x-auto">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Code') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->banks as $bank)
                    <flux:table.row wire:key="bank-{{ $bank->id }}">
                        <flux:table.cell>{{ $bank->name }}</flux:table.cell>
                        <flux:table.cell>{{ $bank->code ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />

                                <flux:menu>
                                    <flux:menu.item icon="pencil" wire:click="edit({{ $bank->id }})">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $bank->id }})">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3">
                            <flux:text>{{ __('No banks registered yet.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="bank-form" :show="$errors->isNotEmpty()" focusable class="max-w-md">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingBankId ? __('Edit bank') : __('New bank') }}
            </flux:heading>

            <flux:input wire:model="name" :label="__('Name')" autofocus />

            <flux:input wire:model="code" :label="__('Code')" :placeholder="__('ISPB or COMPE code')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-bank-deletion" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Delete bank?') }}</flux:heading>
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
