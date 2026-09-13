<?php

namespace App\Enums;

enum InvestmentOperationType: string
{
    case Buy = 'buy';
    case Sell = 'sell';
    case Dividend = 'dividend';
    case Interest = 'interest';
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';

    public function label(): string
    {
        return match ($this) {
            self::Buy => __('Buy'),
            self::Sell => __('Sell'),
            self::Dividend => __('Dividend'),
            self::Interest => __('Interest'),
            self::Deposit => __('Deposit'),
            self::Withdrawal => __('Withdrawal'),
        };
    }

    public function isTrade(): bool
    {
        return $this === self::Buy || $this === self::Sell;
    }

    /**
     * Whether this operation represents money leaving the investor's pocket (true)
     * or money returning to it (false), when linked to a bank account.
     */
    public function isOutgoing(): bool
    {
        return $this === self::Buy || $this === self::Deposit;
    }

    public function color(): string
    {
        return match ($this) {
            self::Buy, self::Deposit => 'green',
            self::Sell, self::Withdrawal => 'red',
            self::Dividend, self::Interest => 'blue',
        };
    }

    /**
     * Operation types that pull money out of a linked bank account.
     *
     * @return array<int, self>
     */
    public static function outgoing(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => $type->isOutgoing()));
    }

    /**
     * Operation types that return money to a linked bank account.
     *
     * @return array<int, self>
     */
    public static function incoming(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => ! $type->isOutgoing()));
    }
}
