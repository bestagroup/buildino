<?php

namespace App\Enums;

enum RoleScopeType: string
{
    case Complex = 'complex';
    case Building = 'building';
    case Unit = 'unit';

    public function label(): string
    {
        return match ($this) {
            self::Complex => 'Complex',
            self::Building => 'Building',
            self::Unit => 'Unit',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
