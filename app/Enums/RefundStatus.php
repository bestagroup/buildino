<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Refunded = 'refunded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Refunded => 'Refunded',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
