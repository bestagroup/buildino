<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case PaymentPending = 'payment_pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PaymentPending => 'Payment Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
            self::Expired => 'Expired',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
