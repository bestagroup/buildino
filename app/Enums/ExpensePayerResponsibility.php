<?php

namespace App\Enums;

enum ExpensePayerResponsibility: string
{
    case Unit = 'unit';
    case Owner = 'owner';
    case Resident = 'resident';

    public function label(): string
    {
        return match ($this) {
            self::Unit => 'واحد (مالک یا ساکن)',
            self::Owner => 'مالک',
            self::Resident => 'ساکن',
        };
    }
}
