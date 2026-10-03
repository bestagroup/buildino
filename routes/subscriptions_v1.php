<?php

use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware([
        'throttle:api-v1',
        'auth:sanctum',
        'user.active',
        'identity.verified',
    ])
    ->group(function (): void {
        Route::get('subscription-plans', [PlanController::class, 'index'])
            ->name('api.v1.subscription-plans.index');
        Route::post('subscription-plans', [PlanController::class, 'store'])
            ->name('api.v1.subscription-plans.store');
        Route::patch('subscription-plans/{plan}', [PlanController::class, 'update'])
            ->name('api.v1.subscription-plans.update');
        Route::get('subscription-features', [PlanController::class, 'features'])
            ->name('api.v1.subscription-features.index');

        Route::get('buildings/{building}/subscriptions', [SubscriptionController::class, 'index'])
            ->name('api.v1.building-subscriptions.index');
        Route::post('buildings/{building}/subscriptions', [SubscriptionController::class, 'store'])
            ->name('api.v1.building-subscriptions.store');
        Route::get('building-subscriptions/{buildingSubscription}', [SubscriptionController::class, 'show'])
            ->name('api.v1.building-subscriptions.show');
        Route::post('building-subscriptions/{buildingSubscription}/renew', [SubscriptionController::class, 'renew'])
            ->name('api.v1.building-subscriptions.renew');
        Route::post('building-subscriptions/{buildingSubscription}/suspend', [SubscriptionController::class, 'suspend'])
            ->name('api.v1.building-subscriptions.suspend');
        Route::post('building-subscriptions/{buildingSubscription}/resume', [SubscriptionController::class, 'resume'])
            ->name('api.v1.building-subscriptions.resume');
        Route::post('building-subscriptions/{buildingSubscription}/cancel', [SubscriptionController::class, 'cancel'])
            ->name('api.v1.building-subscriptions.cancel');
        Route::get('building-subscriptions/{buildingSubscription}/events', [SubscriptionController::class, 'events'])
            ->name('api.v1.building-subscriptions.events');
    });
