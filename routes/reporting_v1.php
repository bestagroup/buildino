<?php

use App\Http\Controllers\Api\V1\ReportingController;
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
            'buildings/{building}/dashboard/management',
            [ReportingController::class, 'managementDashboard']
        );

        Route::get(
            'buildings/{building}/reports/financial-summary',
            [ReportingController::class, 'financialSummary']
        );

        Route::get(
            'buildings/{building}/reports/receivables',
            [ReportingController::class, 'receivables']
        );

        Route::get(
            'buildings/{building}/reports/cash-flow',
            [ReportingController::class, 'cashFlow']
        );

        Route::get(
            'buildings/{building}/reports/facilities',
            [ReportingController::class, 'facilities']
        );

        Route::get(
            'buildings/{building}/reports/services',
            [ReportingController::class, 'services']
        );

        Route::get(
            'platform/reports/summary',
            [ReportingController::class, 'platformSummary']
        );
    });
