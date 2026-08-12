<?php

namespace App\Providers;

use App\Contracts\Notifications\PushSender;
use App\Contracts\Notifications\SmsSender;
use App\Services\Notifications\LogPushSender;
use App\Services\Notifications\LogSmsSender;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsSender::class, LogSmsSender::class);
        $this->app->bind(PushSender::class, LogPushSender::class);
    }
}
