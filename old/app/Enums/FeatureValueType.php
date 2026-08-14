<?php

namespace App\Enums;

enum FeatureValueType: string
{
    case Boolean = 'boolean';
    case Integer = 'integer';
    case String = 'string';
    case Json = 'json';

    public function label(): string
    {
        return match ($this) {
            self::Boolean => 'Boolean',
            self::Integer => 'Integer',
            self::String => 'String',
            self::Json => 'Json',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
