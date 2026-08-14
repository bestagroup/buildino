<?php

use App\Http\Controllers\Api\V1\ServiceMarketplaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware([
        'throttle:api-v1',
        'auth:sanctum',
        'user.active',
        'identity.verified',
    ])
    ->group(function (): void {
        Route::get(
            'buildings/{building}/service-financial-setting',
            [ServiceMarketplaceController::class, 'showSetting']
        );

        Route::put(
            'buildings/{building}/service-financial-setting',
            [ServiceMarketplaceController::class, 'updateSetting']
        );

        Route::post(
            'service-requests/{serviceRequest}/quotes',
            [ServiceMarketplaceController::class, 'quote']
        );

        Route::post(
            'service-request-quotes/{serviceRequestQuote}/accept',
            [ServiceMarketplaceController::class, 'acceptQuote']
        );

        Route::post(
            'service-requests/{serviceRequest}/start',
            [ServiceMarketplaceController::class, 'start']
        );

        Route::post(
            'service-requests/{serviceRequest}/finish',
            [ServiceMarketplaceController::class, 'finish']
        );

        Route::post(
            'service-requests/{serviceRequest}/confirm',
            [ServiceMarketplaceController::class, 'confirm']
        );

        Route::post(
            'service-requests/{serviceRequest}/cancel-financial',
            [ServiceMarketplaceController::class, 'cancel']
        );

        Route::get(
            'platform/service-marketplace-wallet',
            [ServiceMarketplaceController::class, 'platformWallet']
        );
    });
