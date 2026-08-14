<?php

use App\Http\Controllers\Api\V1\ServiceRequestOperationController;
use App\Http\Controllers\Api\V1\SupportConfigurationController;
use App\Http\Controllers\Api\V1\SupportTicketOperationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware([
        'throttle:api-v1',
        'auth:sanctum',
        'user.active',
        'identity.verified',
    ])
    ->group(function (): void {
        Route::middleware('throttle:support')->group(function (): void {
            Route::get(
                'support-tickets/{supportTicket}/messages',
                [SupportTicketOperationController::class, 'messages']
            );

            Route::post(
                'support-tickets/{supportTicket}/messages',
                [SupportTicketOperationController::class, 'addMessage']
            );

            Route::post(
                'support-tickets/{supportTicket}/start',
                [SupportTicketOperationController::class, 'start']
            );

            Route::post(
                'support-tickets/{supportTicket}/wait-user',
                [SupportTicketOperationController::class, 'waitForUser']
            );

            Route::post(
                'support-tickets/{supportTicket}/close',
                [SupportTicketOperationController::class, 'close']
            );

            Route::post(
                'support-tickets/{supportTicket}/reopen',
                [SupportTicketOperationController::class, 'reopen']
            );
        });

        Route::get(
            'support-config/categories',
            [SupportConfigurationController::class, 'categories']
        );

        Route::post(
            'support-config/categories',
            [SupportConfigurationController::class, 'storeCategory']
        );

        Route::patch(
            'support-config/categories/{supportCategory}',
            [SupportConfigurationController::class, 'updateCategory']
        );

        Route::get(
            'support-config/sla-policies',
            [SupportConfigurationController::class, 'slaPolicies']
        );

        Route::post(
            'support-config/sla-policies',
            [SupportConfigurationController::class, 'storeSlaPolicy']
        );

        Route::patch(
            'support-config/sla-policies/{supportSlaPolicy}',
            [SupportConfigurationController::class, 'updateSlaPolicy']
        );

    });
