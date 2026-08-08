<?php

namespace App\Enums;

enum ReportFormat: string
{
    case Pdf = 'pdf';
    case Excel = 'excel';
    case Csv = 'csv';

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'Pdf',
            self::Excel => 'Excel',
            self::Csv => 'Csv',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
