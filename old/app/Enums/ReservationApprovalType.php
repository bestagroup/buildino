<?php

namespace App\Enums;

enum ReservationApprovalType: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Automatic => 'Automatic',
            self::Manual => 'Manual',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
