<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\PushSender;
use Illuminate\Support\Facades\Log;

class LogPushSender implements PushSender
{
    public function send(array $tokens, string $title, string $message, array $data = []): array
    {
        Log::info('Push notification', compact('tokens', 'title', 'message', 'data'));

        return [
            'provider' => 'log',
            'accepted' => true,
            'tokens_count' => count($tokens),
        ];
    }
}
