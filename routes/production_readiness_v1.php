<?php

use App\Http\Controllers\Api\V1\SystemHealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->group(function (): void {
        Route::get(
            'system/readiness',
            [
                SystemHealthController::class,
                'readiness',
            ]
        )->middleware(
            'throttle:system-health'
        );

        Route::middleware([
            'throttle:api-v1',
            'auth:sanctum',
            'user.active',
            'identity.verified',
        ])
            ->group(function (): void {
                Route::get(
                    'admin/system/health',
                    [
                        SystemHealthController::class,
                        'admin',
                    ]
                );
            });
    });
