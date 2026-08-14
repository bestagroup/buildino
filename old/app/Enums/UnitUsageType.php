<?php

namespace App\Enums;

enum UnitUsageType: string
{
    case Residential = 'residential';
    case Commercial = 'commercial';
    case Office = 'office';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Residential => 'Residential',
            self::Commercial => 'Commercial',
            self::Office => 'Office',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
