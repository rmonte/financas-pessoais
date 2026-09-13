<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Closed => __('Closed'),
            self::Paid => __('Paid'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::Closed => 'orange',
            self::Paid => 'green',
        };
    }
}
