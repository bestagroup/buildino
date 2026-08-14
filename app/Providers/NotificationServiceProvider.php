<?php

namespace App\Providers;

use App\Contracts\Notifications\PushSender;
use App\Contracts\Notifications\SmsSender;
use App\Services\Notifications\HttpPushSender;
use App\Services\Notifications\HttpSmsSender;
use App\Services\Notifications\LogPushSender;
use App\Services\Notifications\LogSmsSender;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            SmsSender::class,
            fn () => match ((string) config('notifications.sms_provider', 'log')) {
                'log' => new LogSmsSender(),
                'http' => new HttpSmsSender(),
                default => throw new InvalidArgumentException(
                    'Unsupported SMS provider.'
                ),
            }
        );

        $this->app->singleton(
            PushSender::class,
            fn () => match ((string) config('notifications.push_provider', 'log')) {
                'log' => new LogPushSender(),
                'http' => new HttpPushSender(),
                default => throw new InvalidArgumentException(
                    'Unsupported Push provider.'
                ),
            }
        );
    }
}
