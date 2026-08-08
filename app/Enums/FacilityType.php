<?php

namespace App\Enums;

enum FacilityType: string
{
    case Gym = 'gym';
    case Pool = 'pool';
    case RoofGarden = 'roof_garden';
    case MeetingHall = 'meeting_hall';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Gym => 'Gym',
            self::Pool => 'Pool',
            self::RoofGarden => 'Roof Garden',
            self::MeetingHall => 'Meeting Hall',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
