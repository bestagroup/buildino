<?php

namespace App\Providers;

use App\Contracts\Auth\OtpSender;
use App\Services\Auth\NotificationOtpSender;
use Illuminate\Support\ServiceProvider;

class AuthInfrastructureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            OtpSender::class,
            NotificationOtpSender::class
        );
    }
}
