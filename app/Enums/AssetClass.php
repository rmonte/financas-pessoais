<?php

namespace App\Enums;

enum AssetClass: string
{
    case Equity = 'equity';
    case FixedIncome = 'fixed_income';
    case Pension = 'pension';

    public function label(): string
    {
        return match ($this) {
            self::Equity => __('Equities'),
            self::FixedIncome => __('Fixed income'),
            self::Pension => __('Pension'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Equity => 'blue',
            self::FixedIncome => 'emerald',
            self::Pension => 'violet',
        };
    }
}
