<?php

namespace App\Enums;

enum FinancialTransactionType: string
{
    case Charge = 'charge';
    case Payment = 'payment';
    case Income = 'income';
    case Expense = 'expense';
    case Adjustment = 'adjustment';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Charge => 'Charge',
            self::Payment => 'Payment',
            self::Income => 'Income',
            self::Expense => 'Expense',
            self::Adjustment => 'Adjustment',
            self::Refund => 'Refund',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
