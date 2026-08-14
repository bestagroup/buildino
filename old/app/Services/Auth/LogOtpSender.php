<?php
namespace App\Services\Auth;

use App\Contracts\Auth\OtpSender;
use Illuminate\Support\Facades\Log;

class LogOtpSender implements OtpSender
{
    public function send(string $identifier, string $channel, string $code): void
    {
        Log::channel(config('auth_otp.log_channel'))->info('OTP generated', [
            'identifier' => $identifier,
            'channel' => $channel,
            'code' => $code,
        ]);
    }
}
