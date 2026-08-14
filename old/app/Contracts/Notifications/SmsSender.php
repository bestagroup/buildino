<?php

namespace App\Contracts\Notifications;

interface SmsSender
{
    public function send(string $mobile, string $message): array;
}
