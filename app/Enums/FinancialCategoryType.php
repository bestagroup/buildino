<?php

namespace App\Enums;

enum FinancialCategoryType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Charge = 'charge';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Income',
            self::Expense => 'Expense',
            self::Charge => 'Charge',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
