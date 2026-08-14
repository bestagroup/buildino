<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([
            'user.active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'identity.verified' => \App\Http\Middleware\EnsureVerifiedIdentity::class,
            'building.context' => \App\Http\Middleware\ResolveBuildingContext::class,
            'building.access' => \App\Http\Middleware\EnsureBuildingAccess::class,
            'subscription.active' => \App\Http\Middleware\EnsureSubscriptionIsActive::class,
        ]);
        $middleware->api(
            prepend: [
                \App\Http\Middleware\AssignRequestId::class,
                \App\Http\Middleware\ApplyApiSecurityHeaders::class,
            ]
        );
    })

    ->withExceptions(function (Exceptions $exceptions): void {

        /*
        |--------------------------------------------------------------------------
        | Force JSON for API
        |--------------------------------------------------------------------------
        */

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, \Throwable $e): bool =>
                $request->is('api/*') || $request->expectsJson()
        );

        /*
        |--------------------------------------------------------------------------
        | Validation - 422
        |--------------------------------------------------------------------------
        */

        $exceptions->render(
            function (ValidationException $e, Request $request) {

                if (! $request->is('api/*')) {
                    return null;
                }

                return response()->json([
                    'success' => false,
                    'message' => 'اطلاعات ارسال‌شده معتبر نیست.',
                    'code' => 'VALIDATION_ERROR',
                    'errors' => $e->errors(),
                ], 422);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Authentication - 401
        |--------------------------------------------------------------------------
        */

        $exceptions->render(
            function (AuthenticationException $e, Request $request) {

                if (! $request->is('api/*')) {
                    return null;
                }

                return response()->json([
                    'success' => false,
                    'message' => 'احراز هویت انجام نشده است.',
                    'code' => 'UNAUTHENTICATED',
                ], 401);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Authorization - 403
        |--------------------------------------------------------------------------
        */

        $exceptions->render(
            function (AuthorizationException $e, Request $request) {

                if (! $request->is('api/*')) {
                    return null;
                }

                return response()->json([
                    'success' => false,
                    'message' => 'شما مجوز انجام این عملیات را ندارید.',
                    'code' => 'FORBIDDEN',
                ], 403);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Model Not Found - 404
        |--------------------------------------------------------------------------
        */

        $exceptions->render(
            function (ModelNotFoundException $e, Request $request) {

                if (! $request->is('api/*')) {
                    return null;
                }

                return response()->json([
                    'success' => false,
                    'message' => 'منبع موردنظر یافت نشد.',
                    'code' => 'RESOURCE_NOT_FOUND',
                ], 404);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Route Not Found - 404
        |--------------------------------------------------------------------------
        */

        $exceptions->render(
            function (NotFoundHttpException $e, Request $request) {

                if (! $request->is('api/*')) {
                    return null;
                }

                /*
                 * Laravel prepares Eloquent ModelNotFoundException instances
                 * as Symfony NotFoundHttpException before rendering them.
                 * Preserve the distinction between a missing resource and a
                 * genuinely missing route.
                 */
                if ($e->getPrevious() instanceof ModelNotFoundException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'منبع موردنظر یافت نشد.',
                        'code' => 'RESOURCE_NOT_FOUND',
                    ], 404);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'مسیر API موردنظر یافت نشد.',
                    'code' => 'ROUTE_NOT_FOUND',
                ], 404);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Generic HTTP Exceptions
        |--------------------------------------------------------------------------
        */

        $exceptions->render(
            function (HttpExceptionInterface $e, Request $request) {

                if (! $request->is('api/*')) {
                    return null;
                }

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'خطایی در پردازش درخواست رخ داده است.',
                    'code' => 'HTTP_ERROR',
                ], $e->getStatusCode());
            }
        );
    })

    ->create();
