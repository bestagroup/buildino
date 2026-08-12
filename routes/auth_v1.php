<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {

    Route::post('otp/request', [AuthController::class, 'requestOtp'])
        ->middleware('throttle:otp-request');

    Route::post('otp/login', [AuthController::class, 'loginWithOtp'])
        ->middleware('throttle:auth');

    Route::post('password/login', [AuthController::class, 'loginWithPassword'])
        ->middleware('throttle:auth');

    Route::middleware([
        'auth:sanctum',
        'user.active',
        'identity.verified',
    ])->group(function (): void {

        Route::get('me', [AuthController::class, 'me']);

        Route::post('logout', [AuthController::class, 'logout']);

        Route::post('logout-all', [AuthController::class, 'logoutAll']);
    });
});
