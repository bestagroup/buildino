<?php

/*
Copy the alias() block below into ->withMiddleware(...) in bootstrap/app.php.

use App\Http\Middleware\EnsureBuildingAccess;
use App\Http\Middleware\EnsureSubscriptionIsActive;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureVerifiedIdentity;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\RequestIdMiddleware;
use App\Http\Middleware\ResolveBuildingContext;
use Illuminate\Foundation\Configuration\Middleware;

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
*/
