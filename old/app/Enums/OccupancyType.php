<?php

namespace App\Enums;

enum OccupancyType: string
{
    case Owner = 'owner';
    case Tenant = 'tenant';
    case Resident = 'resident';
    case FamilyMember = 'family_member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Tenant => 'Tenant',
            self::Resident => 'Resident',
            self::FamilyMember => 'Family Member',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
