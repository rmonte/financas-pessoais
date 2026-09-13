<?php

namespace App\Enums;

enum Currency: string
{
    case BRL = 'BRL';
    case USD = 'USD';

    public function label(): string
    {
        return match ($this) {
            self::BRL => __('BRL'),
            self::USD => __('USD'),
        };
    }

    /**
     * Convert an amount in this currency to BRL, using the given USD to BRL exchange rate.
     */
    public function toBrl(float $amount, float $usdRate): float
    {
        return $this === self::USD ? $amount * $usdRate : $amount;
    }
}
