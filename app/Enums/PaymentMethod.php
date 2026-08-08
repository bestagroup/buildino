<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Online = 'online';
    case Manual = 'manual';
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Pos = 'pos';
    case Qr = 'qr';
    case Bill = 'bill';
    case Installment = 'installment';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Manual => 'Manual',
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank Transfer',
            self::Pos => 'Pos',
            self::Qr => 'Qr',
            self::Bill => 'Bill',
            self::Installment => 'Installment',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
