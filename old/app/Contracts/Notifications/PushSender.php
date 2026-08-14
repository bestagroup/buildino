<?php

namespace App\Contracts\Notifications;

interface PushSender
{
    public function send(array $tokens, string $title, string $message, array $data = []): array;
}
