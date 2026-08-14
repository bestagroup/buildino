<?php

namespace App\Enums;

enum LoyaltyTransactionType: string
{
    case Earn = 'earn';
    case Spend = 'spend';
    case Expire = 'expire';
    case Adjust = 'adjust';

    public function label(): string
    {
        return match ($this) {
            self::Earn => 'Earn',
            self::Spend => 'Spend',
            self::Expire => 'Expire',
            self::Adjust => 'Adjust',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
