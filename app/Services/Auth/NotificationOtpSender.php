<?php

namespace App\Services\Auth;

use App\Contracts\Auth\OtpSender;
use App\Contracts\Notifications\SmsSender;
use Illuminate\Support\Facades\Mail;

final class NotificationOtpSender implements OtpSender
{
    public function __construct(
        private readonly SmsSender $sms
    ) {}

    public function send(
        string $identifier,
        string $channel,
        string $code
    ): void {
        $message = sprintf(
            'کد تأیید Buildino: %s',
            $code
        );

        if ($channel === 'sms') {
            $this->sms->send(
                $identifier,
                $message
            );

            return;
        }

        Mail::raw(
            $message,
            fn ($mail) => $mail
                ->to($identifier)
                ->subject('کد تأیید Buildino')
        );
    }
}
