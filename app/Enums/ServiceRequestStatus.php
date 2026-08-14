<?php

namespace App\Enums;

enum ServiceRequestStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Assigned => 'Assigned',
            self::InProgress => 'In Progress',
            self::AwaitingConfirmation => 'Awaiting Confirmation',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
