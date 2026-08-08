<?php

namespace App\Enums;

enum ChargePeriodStatus: string
{
    case Draft = 'draft';
    case Calculating = 'calculating';
    case Issued = 'issued';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Calculating => 'Calculating',
            self::Issued => 'Issued',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
