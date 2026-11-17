<?php

namespace App\Enums;

enum ChargeCalculationType: string
{
    case UnitExpense = 'unit_expense';
    case Fixed = 'fixed';
    case Area = 'area';
    case Persons = 'persons';
    case Equal = 'equal';
    case FloorCoefficient = 'floor_coefficient';

    public function label(): string
    {
        return match ($this) {
            self::UnitExpense => 'بر اساس هزینه ثبت شده برای واحد',
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
