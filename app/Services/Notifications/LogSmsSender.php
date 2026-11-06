<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\SmsSender;
use App\Data\Notifications\SmsTemplateMessage;
use Illuminate\Support\Facades\Log;

class LogSmsSender implements SmsSender
{
    public function send(string $mobile, string|SmsTemplateMessage $message): array
    {
        $masked = strlen($mobile) > 4
            ? str_repeat('*', max(strlen($mobile) - 4, 0)).substr($mobile, -4)
            : '****';

        if ($message instanceof SmsTemplateMessage) {
            app(SmsTemplateRegistry::class)->payload($message);
        }

        Log::info('SMS notification (log provider)', [
            'recipient' => $masked,
            'purpose' => $message instanceof SmsTemplateMessage ? $message->purpose : 'text',
            // Never write OTPs, invitation codes or recipient details to logs.
        ]);

        return [
            'provider' => 'log',
            'recipient' => $masked,
            'accepted' => true,
        ];
    }
}
