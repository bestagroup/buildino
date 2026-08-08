<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Processing => 'Processing',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
