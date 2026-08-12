<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['throttle:api-v1'])
    ->group(function (): void {
        // Public auth routes.
        Route::middleware('throttle:otp-request')
            ->post('auth/otp/request', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'requestOtp']);

        Route::middleware('throttle:auth')
            ->post('auth/otp/login', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'loginWithOtp']);

        Route::middleware('throttle:auth')
            ->post('auth/password/login', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'loginWithPassword']);

        // Authenticated API routes.
        Route::middleware([
            'auth:sanctum',
            'user.active',
            'identity.verified',
        ])->group(function (): void {
            // Building-bound routes may additionally use:
            // 'building.context', 'building.access', 'subscription.active'
        });
    });
