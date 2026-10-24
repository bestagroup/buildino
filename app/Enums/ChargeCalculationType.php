<?php

namespace App\Enums;

enum ChargeCalculationType: string
{
    case Fixed = 'fixed';
    case Area = 'area';
    case Persons = 'persons';
    case Equal = 'equal';
    case FloorCoefficient = 'floor_coefficient';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed',
            self::Area => 'Area',
            self::Persons => 'Persons',
            self::Equal => 'Equal',
            self::FloorCoefficient => 'Floor coefficient',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
