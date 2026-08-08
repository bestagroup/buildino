<?php

namespace App\Enums;

enum AnnouncementType: string
{
    case General = 'general';
    case Urgent = 'urgent';
    case Maintenance = 'maintenance';
    case Financial = 'financial';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Urgent => 'Urgent',
            self::Maintenance => 'Maintenance',
            self::Financial => 'Financial',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
