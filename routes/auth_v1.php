<?php
use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {
    Route::post('otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:5,1');
    Route::post('otp/login', [AuthController::class, 'loginWithOtp'])->middleware('throttle:10,1');
    Route::post('password/login', [AuthController::class, 'loginWithPassword'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
    });
});
