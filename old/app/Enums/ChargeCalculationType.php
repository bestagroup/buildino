<?php

namespace App\Enums;

enum ChargeCalculationType: string
{
    case Fixed = 'fixed';
    case Area = 'area';
    case Persons = 'persons';
    case Equal = 'equal';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed',
            self::Area => 'Area',
            self::Persons => 'Persons',
            self::Equal => 'Equal',
            self::Custom => 'Custom',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
