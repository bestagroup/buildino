<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class ApiSecurityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('api-v1', function (Request $request): Limit {
            return Limit::perMinute((int) config('api_security.default_rate_limit', 120))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request): Limit {
            return Limit::perMinute((int) config('api_security.auth_rate_limit', 10))
                ->by(strtolower((string) $request->input(
                    'login',
                    $request->input('mobile', $request->ip())
                )));
        });

        RateLimiter::for('otp-request', function (Request $request): array {
            $identifier = strtolower(trim((string) $request->input(
                'identifier',
                $request->input('mobile', 'unknown')
            )));

            return [
                Limit::perMinute((int) config('api_security.otp_request_rate_limit', 5))
                    ->by('identifier:'.$identifier),
                Limit::perMinute(20)
                    ->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('system-health', function (Request $request): Limit {
            return Limit::perMinute(30)
                ->by($request->ip());
        });

        RateLimiter::for('support', function (Request $request): Limit {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('notifications', function (Request $request): Limit {
            return Limit::perMinute(90)
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('payments', function (Request $request): Limit {
            return Limit::perMinute((int) config('api_security.payment_rate_limit', 30))
                ->by($request->user()?->id ?: $request->ip());
        });
    }
}
