<?php

use App\Http\Controllers\Web\ManagementAuthController;
use App\Http\Controllers\Web\ManagementDashboardController;
use App\Http\Middleware\EnsureManagementWebAccess;
use Illuminate\Support\Facades\Route;

Route::redirect(
    '/',
    '/management'
);

Route::middleware('guest')
    ->group(function (): void {
        Route::get(
            '/management/login',
            [
                ManagementAuthController::class,
                'create',
            ]
        )->name('login');

        Route::post(
            '/management/login',
            [
                ManagementAuthController::class,
                'store',
            ]
        )
            ->middleware('throttle:auth')
            ->name(
                'management.login.store'
            );
    });

Route::middleware([
    'auth',
    EnsureManagementWebAccess::class,
])
    ->prefix('management')
    ->name('management.')
    ->group(function (): void {
        Route::get(
            '/',
            [
                ManagementDashboardController::class,
                'index',
            ]
        )->name('dashboard');

        Route::post(
            '/logout',
            [
                ManagementAuthController::class,
                'destroy',
            ]
        )->name('logout');
    });
