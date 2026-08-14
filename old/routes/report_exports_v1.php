<?php

use App\Http\Controllers\Api\V1\ReportExportController;
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
            'report-exports',
            [ReportExportController::class, 'index']
        );

        Route::post(
            'report-definitions/{reportDefinition}/exports',
            [ReportExportController::class, 'store']
        );

        Route::get(
            'report-exports/{generatedReport}',
            [ReportExportController::class, 'show']
        );

        Route::post(
            'report-exports/{generatedReport}/retry',
            [ReportExportController::class, 'retry']
        );

        Route::get(
            'report-exports/{generatedReport}/download',
            [ReportExportController::class, 'download']
        );

        Route::delete(
            'report-exports/{generatedReport}',
            [ReportExportController::class, 'destroy']
        );
    });
