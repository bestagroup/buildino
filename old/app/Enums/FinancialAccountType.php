<?php

namespace App\Enums;

enum FinancialAccountType: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Fund = 'fund';
    case Receivable = 'receivable';
    case Payable = 'payable';
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Bank => 'Bank',
            self::Fund => 'Fund',
            self::Receivable => 'Receivable',
            self::Payable => 'Payable',
            self::Income => 'Income',
            self::Expense => 'Expense',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
