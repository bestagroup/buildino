<?php

namespace App\Enums;

enum FinancialAdjustmentType: string
{
    case Discount = 'discount';
    case Penalty = 'penalty';
    case Waiver = 'waiver';
    case Correction = 'correction';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Discount => 'Discount',
            self::Penalty => 'Penalty',
            self::Waiver => 'Waiver',
            self::Correction => 'Correction',
            self::Refund => 'Refund',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
