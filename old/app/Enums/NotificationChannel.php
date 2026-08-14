<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case Database = 'database';
    case Sms = 'sms';
    case Email = 'email';
    case Push = 'push';

    public function label(): string
    {
        return match ($this) {
            self::Database => 'Database',
            self::Sms => 'Sms',
            self::Email => 'Email',
            self::Push => 'Push',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
