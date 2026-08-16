<?php

use App\Http\Controllers\Web\ManagementAuthController;
use App\Http\Controllers\Web\ManagementDashboardController;
use App\Http\Controllers\Web\ManagementLookupController;
use App\Http\Controllers\Web\ManagementOperationsController;
use App\Http\Controllers\Web\ManagementPasswordResetController;
use App\Http\Controllers\Web\ManagementUserDataController;
use App\Http\Controllers\Web\PortalAuthController;
use App\Http\Controllers\Web\PortalDashboardController;
use App\Http\Controllers\Web\PortalOperationsController;
use App\Http\Controllers\Web\WebDataTableController;
use App\Http\Middleware\EnsureManagementWebAccess;
use App\Http\Middleware\EnsurePortalWebAccess;
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


        /*
        |--------------------------------------------------------------------------
        | Management Password Reset
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/management/forgot-password',
            [
                ManagementPasswordResetController::class,
                'requestForm',
            ]
        )->name('password.request');

        Route::post(
            '/management/forgot-password',
            [
                ManagementPasswordResetController::class,
                'sendResetLink',
            ]
        )
            ->middleware('throttle:auth')
            ->name('password.email');

        Route::get(
            '/management/reset-password/{token}',
            [
                ManagementPasswordResetController::class,
                'resetForm',
            ]
        )->name('password.reset');

        Route::post(
            '/management/reset-password',
            [
                ManagementPasswordResetController::class,
                'reset',
            ]
        )
            ->middleware('throttle:auth')
            ->name('password.update');
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

        /*
        |--------------------------------------------------------------------------
        | Server-side Dashboard DataTables
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/datatables/{table}',
            [
                WebDataTableController::class,
                'management',
            ]
        )
            ->middleware('throttle:api-v1')
            ->name('datatables');

        /*
        |--------------------------------------------------------------------------
        | Operational CRUD Center
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/operations',
            [
                ManagementOperationsController::class,
                'index',
            ]
        )->name('operations.index');

        Route::get(
            '/operations/{resource}',
            [
                ManagementOperationsController::class,
                'show',
            ]
        )->name('operations.show');

        Route::get(
            '/lookups/{type}',
            ManagementLookupController::class
        )
            ->middleware('throttle:api-v1')
            ->name('lookups');

        /*
        |--------------------------------------------------------------------------
        | Web-only administration endpoints
        |--------------------------------------------------------------------------
        |
        | User / Role CRUD did not have a dedicated API controller in the
        | stabilized backend. These endpoints reuse the same PermissionChecker
        | and are protected by the authenticated web management middleware.
        |
        */

        Route::prefix('data')
            ->middleware('throttle:api-v1')
            ->group(function (): void {
                Route::get(
                    'users',
                    [
                        ManagementUserDataController::class,
                        'users',
                    ]
                );

                Route::post(
                    'users',
                    [
                        ManagementUserDataController::class,
                        'storeUser',
                    ]
                );

                Route::patch(
                    'users/{user}',
                    [
                        ManagementUserDataController::class,
                        'updateUser',
                    ]
                );

                Route::delete(
                    'users/{user}',
                    [
                        ManagementUserDataController::class,
                        'destroyUser',
                    ]
                );

                Route::get(
                    'roles',
                    [
                        ManagementUserDataController::class,
                        'roles',
                    ]
                );

                Route::post(
                    'roles',
                    [
                        ManagementUserDataController::class,
                        'storeRole',
                    ]
                );

                Route::patch(
                    'roles/{role}',
                    [
                        ManagementUserDataController::class,
                        'updateRole',
                    ]
                );

                Route::delete(
                    'roles/{role}',
                    [
                        ManagementUserDataController::class,
                        'destroyRole',
                    ]
                );

                Route::get(
                    'role-assignments',
                    [
                        ManagementUserDataController::class,
                        'assignments',
                    ]
                );

                Route::post(
                    'role-assignments',
                    [
                        ManagementUserDataController::class,
                        'storeAssignment',
                    ]
                );

                Route::patch(
                    'role-assignments/{assignment}',
                    [
                        ManagementUserDataController::class,
                        'updateAssignment',
                    ]
                );

                Route::delete(
                    'role-assignments/{assignment}',
                    [
                        ManagementUserDataController::class,
                        'destroyAssignment',
                    ]
                );
            });

        Route::post(
            '/logout',
            [
                ManagementAuthController::class,
                'destroy',
            ]
        )->name('logout');
    });


/*
|--------------------------------------------------------------------------
| Resident / Provider Portal
|--------------------------------------------------------------------------
|
| The portal deliberately uses the same web session guard but a separate
| access boundary from the management dashboard. Resident access is derived
| from active UnitOwnership / UnitOccupancy relationships. Provider access
| is derived from the service_provider role or assigned service requests.
|
*/

Route::get(
    '/portal/login',
    [
        PortalAuthController::class,
        'create',
    ]
)->name('portal.login');

Route::post(
    '/portal/login',
    [
        PortalAuthController::class,
        'store',
    ]
)
    ->middleware('throttle:auth')
    ->name('portal.login.store');

Route::prefix('portal')
    ->name('portal.')
    ->group(function (): void {
        Route::get(
            '/',
            [
                PortalDashboardController::class,
                'index',
            ]
        )
            ->middleware(
                EnsurePortalWebAccess::class
            )
            ->name('dashboard');

        Route::get(
            '/resident',
            [
                PortalDashboardController::class,
                'resident',
            ]
        )
            ->middleware(
                EnsurePortalWebAccess::class
                . ':resident'
            )
            ->name(
                'resident.dashboard'
            );

        Route::get(
            '/resident/datatables/{table}',
            [
                WebDataTableController::class,
                'resident',
            ]
        )
            ->middleware([
                EnsurePortalWebAccess::class
                . ':resident',
                'throttle:api-v1',
            ])
            ->name(
                'resident.datatables'
            );

        Route::get(
            '/resident/operations/{resource}',
            [
                PortalOperationsController::class,
                'residentIndex',
            ]
        )
            ->middleware(
                EnsurePortalWebAccess::class
                . ':resident'
            )
            ->name(
                'resident.operations.index'
            );

        Route::get(
            '/resident/operations/{resource}/{id}',
            [
                PortalOperationsController::class,
                'residentShow',
            ]
        )
            ->whereNumber('id')
            ->middleware(
                EnsurePortalWebAccess::class
                . ':resident'
            )
            ->name(
                'resident.operations.show'
            );

        Route::get(
            '/provider',
            [
                PortalDashboardController::class,
                'provider',
            ]
        )
            ->middleware(
                EnsurePortalWebAccess::class
                . ':provider'
            )
            ->name(
                'provider.dashboard'
            );

        Route::get(
            '/provider/datatables/{table}',
            [
                WebDataTableController::class,
                'provider',
            ]
        )
            ->middleware([
                EnsurePortalWebAccess::class
                . ':provider',
                'throttle:api-v1',
            ])
            ->name(
                'provider.datatables'
            );

        Route::get(
            '/provider/operations/{resource}',
            [
                PortalOperationsController::class,
                'providerIndex',
            ]
        )
            ->middleware(
                EnsurePortalWebAccess::class
                . ':provider'
            )
            ->name(
                'provider.operations.index'
            );

        Route::get(
            '/provider/operations/{resource}/{id}',
            [
                PortalOperationsController::class,
                'providerShow',
            ]
        )
            ->whereNumber('id')
            ->middleware(
                EnsurePortalWebAccess::class
                . ':provider'
            )
            ->name(
                'provider.operations.show'
            );

        Route::post(
            '/logout',
            [
                PortalAuthController::class,
                'destroy',
            ]
        )
            ->middleware(
                EnsurePortalWebAccess::class
            )
            ->name('logout');
    });
