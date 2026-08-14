<?php

use App\Http\Controllers\Api\V1\PaymentGatewayController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public PSP return channels
|--------------------------------------------------------------------------
|
| These routes intentionally do NOT use auth:sanctum.
| A browser callback is never trusted as proof of payment; it only causes
| a server-to-server verification. Webhooks additionally require a signed
| request verified by the configured PaymentGateway adapter.
|
*/

Route::prefix(
    'v1/payment-gateways/{gateway}'
)
    ->middleware('throttle:30,1')
    ->group(function (): void {
        Route::match(
            ['GET', 'POST'],
            'callback',
            [
                PaymentGatewayController::class,
                'callback',
            ]
        );

        Route::post(
            'webhook',
            [
                PaymentGatewayController::class,
                'webhook',
            ]
        );
    });

Route::prefix('v1')
    ->middleware([
        'throttle:api-v1',
        'auth:sanctum',
        'user.active',
        'identity.verified',
    ])
    ->group(function (): void {
        Route::post(
            'payments/{payment}/gateway/initiate',
            [
                PaymentGatewayController::class,
                'initiate',
            ]
        )->middleware(
            'throttle:payments'
        );

        Route::get(
            'admin/payment-gateway-events',
            [
                PaymentGatewayController::class,
                'events',
            ]
        );
    });
