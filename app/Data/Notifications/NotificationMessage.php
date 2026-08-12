<?php

namespace App\Data\Notifications;

final readonly class NotificationMessage
{
    public function __construct(
        public string $type,
        public string $title,
        public string $message,
        public array $data = [],
    ) {}
}
