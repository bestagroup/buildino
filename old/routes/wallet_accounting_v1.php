<?php

use App\Http\Controllers\Api\V1\WalletAccountingController;
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
            'buildings/{building}/wallet-accounting-profile',
            [WalletAccountingController::class, 'profile']
        );

        Route::put(
            'buildings/{building}/wallet-accounting-profile',
            [WalletAccountingController::class, 'updateProfile']
        );

        Route::get(
            'wallet-transfers/{walletTransfer}/accounting-posting',
            [WalletAccountingController::class, 'posting']
        );

        Route::post(
            'wallet-transfers/{walletTransfer}/accounting-post',
            [WalletAccountingController::class, 'post']
        );
    });
