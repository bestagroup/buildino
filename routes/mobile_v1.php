<?php

use App\Http\Controllers\Api\V1\MobileBootstrapController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/mobile')
    ->middleware([
        'throttle:api-v1',
        'auth:sanctum',
        'user.active',
        'identity.verified',
    ])
    ->group(function (): void {
        Route::get(
            'bootstrap',
            MobileBootstrapController::class
        )->name(
            'api.v1.mobile.bootstrap'
        );
    });
