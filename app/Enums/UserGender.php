<?php

namespace App\Enums;

enum UserGender: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
