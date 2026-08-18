<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\ApplyApiSecurityHeaders;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureBuildingAccess;
use App\Http\Middleware\EnsureSubscriptionIsActive;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureVerifiedIdentity;
use App\Http\Middleware\ResolveBuildingContext;
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
            'user.active' => EnsureUserIsActive::class,
            'identity.verified' => EnsureVerifiedIdentity::class,
            'building.context' => ResolveBuildingContext::class,
            'building.access' => EnsureBuildingAccess::class,
            'subscription.active' => EnsureSubscriptionIsActive::class,
        ]);
        /*
         * The management CRUD pages are first-party browser clients of the
         * existing /api/v1 contract. Enable Sanctum's stateful frontend
         * middleware so the authenticated web session + CSRF token can be
         * reused safely by same-origin API requests.
         */
        $middleware->statefulApi();

        $middleware->api(
            prepend: [
                AssignRequestId::class,
                ApplyApiSecurityHeaders::class,
            ]
        );
    })

    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(
            function (ApiException $e, Request $request) {
                if (! $request->is('api/*')) {
                    return null;
                }

                return response()->json([
                    'code' => $e->errorCode,
                    'message' => $e->getMessage(),
                ], $e->status);
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Force JSON for API
        |--------------------------------------------------------------------------
        */

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e): bool => $request->is('api/*') || $request->expectsJson()
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
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Validation failed.',
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
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication is required.',
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
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to perform this action.',
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

                $status = $e->getStatusCode();
                $code = match ($status) {
                    401 => 'UNAUTHENTICATED',
                    403 => 'FORBIDDEN',
                    429 => 'RATE_LIMITED',
                    default => 'HTTP_ERROR',
                };

                $message = match ($status) {
                    401 => 'Authentication is required.',
                    403 => 'You do not have permission to perform this action.',
                    429 => 'Too many requests.',
                    default => $e->getMessage() ?: 'The request could not be processed.',
                };

                return response()->json([
                    'code' => $code,
                    'message' => $message,
                ], $status);
            }
        );
    })

    ->create();
