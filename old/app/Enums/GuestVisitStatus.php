<?php

namespace App\Enums;

enum GuestVisitStatus: string
{
    case Invited = 'invited';
    case Entered = 'entered';
    case Exited = 'exited';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::Entered => 'Entered',
            self::Exited => 'Exited',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
