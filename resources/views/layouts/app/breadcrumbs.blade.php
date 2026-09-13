@php
    $routeName = request()->route()?->getName();
@endphp

<flux:breadcrumbs>
    <flux:breadcrumbs.item :href="route('dashboard')" icon="home" icon:variant="outline" wire:navigate />

    @switch($routeName)
        @case('accounts.index')
            <flux:breadcrumbs.item>{{ __('Accounts') }}</flux:breadcrumbs.item>
            @break

        @case('transactions.index')
            <flux:breadcrumbs.item>{{ __('Transactions') }}</flux:breadcrumbs.item>
            @break

        @case('invoices.index')
            <flux:breadcrumbs.item>{{ __('Invoices') }}</flux:breadcrumbs.item>
            @break

        @case('invoices.show')
            <flux:breadcrumbs.item :href="route('invoices.index')" wire:navigate>{{ __('Invoices') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>
                {{ str_pad((string) request()->route('invoice')->reference_month, 2, '0', STR_PAD_LEFT) }}/{{ request()->route('invoice')->reference_year }}
            </flux:breadcrumbs.item>
            @break

        @case('recurring-transactions.index')
            <flux:breadcrumbs.item>{{ __('Recurring') }}</flux:breadcrumbs.item>
            @break

        @case('categories.index')
            <flux:breadcrumbs.item>{{ __('Categories') }}</flux:breadcrumbs.item>
            @break

        @case('banks.index')
            <flux:breadcrumbs.item>{{ __('Banks') }}</flux:breadcrumbs.item>
            @break

        @case('investments.index')
            <flux:breadcrumbs.item>{{ __('Investments') }}</flux:breadcrumbs.item>
            @break

        @case('investments.show')
            <flux:breadcrumbs.item :href="route('investments.index')" wire:navigate>{{ __('Investments') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ request()->route('investment')->name }}</flux:breadcrumbs.item>
            @break

        @case('profile.edit')
        @case('appearance.edit')
        @case('security.edit')
            <flux:breadcrumbs.item>{{ __('Settings') }}</flux:breadcrumbs.item>
            @break
    @endswitch
</flux:breadcrumbs>
