<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
    case Investment = 'investment';

    public function label(): string
    {
        return match ($this) {
            self::Income => __('Income'),
            self::Expense => __('Expense'),
            self::Transfer => __('Transfer'),
            self::Investment => __('Investment'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Income => 'green',
            self::Expense => 'red',
            self::Transfer => 'blue',
            self::Investment => 'violet',
        };
    }

    /**
     * The types a user can pick when manually creating a transaction. Investment transactions
     * are only ever created by the system, alongside an investment operation.
     *
     * @return array<int, self>
     */
    public static function manuallyCreatable(): array
    {
        return [self::Income, self::Expense, self::Transfer];
    }
}
