<?php

namespace App\Enums;

enum InvitationChannel: string
{
    case Sms = 'sms';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Sms => 'Sms',
            self::Email => 'Email',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
