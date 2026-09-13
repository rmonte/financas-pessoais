<?php

namespace App\Console\Commands;

use App\Models\RecurringTransaction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:generate-recurring-transactions')]
#[Description('Generate transactions for every recurring rule due up to the current month')]
class GenerateRecurringTransactions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        RecurringTransaction::generateAllDue();

        $this->info('Recurring transactions generated.');
    }
}
