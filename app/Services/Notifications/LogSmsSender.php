<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\SmsSender;
use Illuminate\Support\Facades\Log;

class LogSmsSender implements SmsSender
{
    public function send(string $mobile, string $message): array
    {
        Log::info('SMS notification', compact('mobile', 'message'));

        return [
            'provider' => 'log',
            'recipient' => $mobile,
            'accepted' => true,
        ];
    }
}
