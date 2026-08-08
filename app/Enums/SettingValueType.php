<?php

namespace App\Enums;

enum SettingValueType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Json = 'json';
    case Float = 'float';

    public function label(): string
    {
        return match ($this) {
            self::String => 'String',
            self::Integer => 'Integer',
            self::Boolean => 'Boolean',
            self::Json => 'Json',
            self::Float => 'Float',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
