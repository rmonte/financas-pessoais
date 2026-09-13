<?php

use App\Livewire\Accounts\Index as AccountsIndex;
use App\Livewire\Banks\Index as BanksIndex;
use App\Livewire\Categories\Index as CategoriesIndex;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Investments\Index as InvestmentsIndex;
use App\Livewire\Investments\Show as InvestmentsShow;
use App\Livewire\Invoices\Index as InvoicesIndex;
use App\Livewire\Invoices\Show as InvoicesShow;
use App\Livewire\RecurringTransactions\Index as RecurringTransactionsIndex;
use App\Livewire\Transactions\Index as TransactionsIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', DashboardIndex::class)->name('dashboard');
    Route::livewire('banks', BanksIndex::class)->name('banks.index');
    Route::livewire('accounts', AccountsIndex::class)->name('accounts.index');
    Route::livewire('categories', CategoriesIndex::class)->name('categories.index');
    Route::livewire('transactions', TransactionsIndex::class)->name('transactions.index');
    Route::livewire('invoices', InvoicesIndex::class)->name('invoices.index');
    Route::livewire('invoices/{invoice}', InvoicesShow::class)->name('invoices.show');
    Route::livewire('recurring-transactions', RecurringTransactionsIndex::class)->name('recurring-transactions.index');
    Route::livewire('investments', InvestmentsIndex::class)->name('investments.index');
    Route::livewire('investments/{investment}', InvestmentsShow::class)->name('investments.show');
});

require __DIR__.'/settings.php';
