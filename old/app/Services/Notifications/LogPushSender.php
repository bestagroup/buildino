<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\PushSender;
use Illuminate\Support\Facades\Log;

class LogPushSender implements PushSender
{
    public function send(
        array $tokens,
        string $title,
        string $message,
        array $data = []
    ): array {
        Log::info('Push notification (log provider)', [
            'tokens_count' => count(array_unique($tokens)),
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        return [
            'provider' => 'log',
            'accepted' => true,
            'tokens_count' => count(array_unique($tokens)),
        ];
    }
}
