<?php

namespace App\Enums;

enum ParkingType: string
{
    case Private = 'private';
    case Shared = 'shared';
    case Guest = 'guest';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Private',
            self::Shared => 'Shared',
            self::Guest => 'Guest',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
