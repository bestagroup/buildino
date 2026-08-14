<?php

use App\Http\Controllers\Api\V1\ProviderBankAccountController;
use App\Http\Controllers\Api\V1\ProviderPayoutController;
use App\Http\Controllers\Api\V1\WalletReconciliationController;
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
            'provider/bank-accounts',
            [ProviderBankAccountController::class, 'index']
        );

        Route::post(
            'provider/bank-accounts',
            [ProviderBankAccountController::class, 'store']
        );

        Route::post(
            'provider-bank-accounts/{providerBankAccount}/verify',
            [ProviderBankAccountController::class, 'verify']
        );

        Route::get(
            'provider/payouts',
            [ProviderPayoutController::class, 'mine']
        );

        Route::post(
            'provider/payouts',
            [ProviderPayoutController::class, 'store']
        );

        Route::get(
            'admin/provider-payouts',
            [ProviderPayoutController::class, 'adminIndex']
        );

        Route::post(
            'provider-payouts/{providerPayoutRequest}/approve',
            [ProviderPayoutController::class, 'approve']
        );

        Route::post(
            'provider-payouts/{providerPayoutRequest}/reject',
            [ProviderPayoutController::class, 'reject']
        );

        Route::post(
            'provider-payouts/{providerPayoutRequest}/paid',
            [ProviderPayoutController::class, 'markPaid']
        );

        Route::get(
            'wallets/{wallet}/reconciliations',
            [WalletReconciliationController::class, 'index']
        );

        Route::post(
            'wallets/{wallet}/reconcile',
            [WalletReconciliationController::class, 'run']
        );
    });
