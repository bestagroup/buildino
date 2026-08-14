<?php

use App\Http\Controllers\Api\V1\BuildingBankAccountController;
use App\Http\Controllers\Api\V1\BuildingBillPaymentController;
use App\Http\Controllers\Api\V1\FacilityReservationController;
use App\Http\Controllers\Api\V1\WalletPayoutController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\WalletTopUpController;
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
            'wallets/me',
            [WalletController::class, 'me']
        );

        Route::get(
            'wallets/{wallet}/entries',
            [WalletController::class, 'entries']
        );

        Route::get(
            'units/{unit}/wallet',
            [WalletController::class, 'unit']
        );

        Route::get(
            'buildings/{building}/wallet',
            [WalletController::class, 'building']
        );


        Route::post(
            'buildings/{building}/wallet-topups',
            [WalletTopUpController::class, 'store']
        );

        Route::get(
            'wallet-topups/{walletTopUp}',
            [WalletTopUpController::class, 'show']
        );

        Route::post(
            'facility-reservations/{facilityReservation}/pay',
            [FacilityReservationController::class, 'pay']
        );

        Route::get(
            'buildings/{building}/bank-accounts',
            [BuildingBankAccountController::class, 'index']
        );

        Route::post(
            'buildings/{building}/bank-accounts',
            [BuildingBankAccountController::class, 'store']
        );

        Route::post(
            'building-bank-accounts/{buildingBankAccount}/verify',
            [BuildingBankAccountController::class, 'verify']
        );

        Route::get(
            'buildings/{building}/wallet-payouts',
            [WalletPayoutController::class, 'index']
        );

        Route::post(
            'buildings/{building}/wallet-payouts',
            [WalletPayoutController::class, 'store']
        );

        Route::post(
            'wallet-payouts/{walletPayoutRequest}/approve',
            [WalletPayoutController::class, 'approve']
        );

        Route::post(
            'wallet-payouts/{walletPayoutRequest}/reject',
            [WalletPayoutController::class, 'reject']
        );

        Route::post(
            'wallet-payouts/{walletPayoutRequest}/paid',
            [WalletPayoutController::class, 'markPaid']
        );

        Route::get(
            'buildings/{building}/bill-payments',
            [BuildingBillPaymentController::class, 'index']
        );

        Route::post(
            'buildings/{building}/bill-payments',
            [BuildingBillPaymentController::class, 'store']
        );

        Route::post(
            'building-bill-payments/{buildingBillPayment}/complete',
            [BuildingBillPaymentController::class, 'complete']
        );

        Route::post(
            'building-bill-payments/{buildingBillPayment}/fail',
            [BuildingBillPaymentController::class, 'fail']
        );
    });
