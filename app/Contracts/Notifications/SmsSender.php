<?php

namespace App\Contracts\Notifications;

use App\Data\Notifications\SmsTemplateMessage;

interface SmsSender
{
    public function send(string $mobile, string|SmsTemplateMessage $message): array;
}
