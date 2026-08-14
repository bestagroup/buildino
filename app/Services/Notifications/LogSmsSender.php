<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\SmsSender;
use Illuminate\Support\Facades\Log;

class LogSmsSender implements SmsSender
{
    public function send(string $mobile, string $message): array
    {
        $masked = strlen($mobile) > 4
            ? str_repeat('*', max(strlen($mobile) - 4, 0)).substr($mobile, -4)
            : '****';

        Log::info('SMS notification (log provider)', [
            'recipient' => $masked,
            'message' => $message,
        ]);

        return [
            'provider' => 'log',
            'recipient' => $masked,
            'accepted' => true,
        ];
    }
}
