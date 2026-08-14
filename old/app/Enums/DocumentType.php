<?php

namespace App\Enums;

enum DocumentType: string
{
    case Building = 'building';
    case Unit = 'unit';
    case Contract = 'contract';
    case Ownership = 'ownership';
    case Lease = 'lease';
    case MeetingMinute = 'meeting_minute';
    case Financial = 'financial';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Building => 'Building',
            self::Unit => 'Unit',
            self::Contract => 'Contract',
            self::Ownership => 'Ownership',
            self::Lease => 'Lease',
            self::MeetingMinute => 'Meeting Minute',
            self::Financial => 'Financial',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
