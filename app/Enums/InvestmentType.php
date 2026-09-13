<?php

namespace App\Enums;

enum InvestmentType: string
{
    case Stock = 'stock';
    case Treasury = 'treasury';
    case Pension = 'pension';
    case FixedIncome = 'fixed_income';

    public function label(): string
    {
        return match ($this) {
            self::Stock => __('Stock'),
            self::Treasury => __('Treasury bond'),
            self::Pension => __('Pension'),
            self::FixedIncome => __('Fixed income'),
        };
    }

    /**
     * The operation types that are valid for this investment type.
     *
     * @return array<int, InvestmentOperationType>
     */
    public function operationTypes(): array
    {
        return match ($this) {
            self::Stock => [InvestmentOperationType::Buy, InvestmentOperationType::Sell, InvestmentOperationType::Dividend, InvestmentOperationType::Interest],
            self::Treasury => [InvestmentOperationType::Buy, InvestmentOperationType::Sell, InvestmentOperationType::Interest],
            self::Pension => [InvestmentOperationType::Buy, InvestmentOperationType::Sell],
            self::FixedIncome => [InvestmentOperationType::Deposit, InvestmentOperationType::Withdrawal, InvestmentOperationType::Interest],
        };
    }

    /**
     * Whether the position is valued as quantity x price (a share/quota that fluctuates in value)
     * rather than as an accumulated balance from deposits, withdrawals, and interest.
     */
    public function isQuantityBased(): bool
    {
        return match ($this) {
            self::Stock, self::Treasury, self::Pension => true,
            self::FixedIncome => false,
        };
    }

    /**
     * The broader asset class used to report how patrimony is allocated.
     */
    public function assetClass(): AssetClass
    {
        return match ($this) {
            self::Stock => AssetClass::Equity,
            self::Treasury, self::FixedIncome => AssetClass::FixedIncome,
            self::Pension => AssetClass::Pension,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Stock => 'blue',
            self::Treasury => 'amber',
            self::Pension => 'violet',
            self::FixedIncome => 'emerald',
        };
    }
}
