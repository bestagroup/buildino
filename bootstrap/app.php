<?php

use App\Http\Middleware\EnsureBuildingAccess;
use App\Http\Middleware\EnsureSubscriptionIsActive;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureVerifiedIdentity;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\RequestIdMiddleware;
use App\Http\Middleware\ResolveBuildingContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            RequestIdMiddleware::class,
            ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'user.active' => EnsureUserIsActive::class,
            'identity.verified' => EnsureVerifiedIdentity::class,
            'building.context' => ResolveBuildingContext::class,
            'building.access' => EnsureBuildingAccess::class,
            'subscription.active' => EnsureSubscriptionIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
