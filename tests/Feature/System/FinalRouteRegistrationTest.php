<?php

namespace Tests\Feature\System;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FinalRouteRegistrationTest extends TestCase
{
    public function test_mobile_bootstrap_route_is_registered_exactly_once(): void
    {
        $matches = collect(Route::getRoutes()->getRoutes())
            ->filter(
                fn ($route): bool =>
                    $route->uri() === 'api/v1/mobile/bootstrap'
                    && in_array('GET', $route->methods(), true)
            );

        $this->assertCount(1, $matches);

        $this->assertSame(
            'api.v1.mobile.bootstrap',
            $matches->first()?->getName()
        );
    }

    public function test_final_notification_and_service_assignment_routes_are_registered(): void
    {
        $this->assertTrue(
            Route::has(
                'api.v1.notification-devices.store'
            ),
            'POST /api/v1/notification-devices is not registered.'
        );

        $this->assertTrue(
            Route::has(
                'api.v1.service-requests.assign'
            ),
            'POST /api/v1/service-requests/{serviceRequest}/assign is not registered.'
        );

        $notification = Route::getRoutes()->match(
            Request::create(
                '/api/v1/notification-devices',
                'POST'
            )
        );

        $this->assertSame(
            'api.v1.notification-devices.store',
            $notification->getName()
        );

        $serviceAssignment = Route::getRoutes()->match(
            Request::create(
                '/api/v1/service-requests/123/assign',
                'POST'
            )
        );

        $this->assertSame(
            'api.v1.service-requests.assign',
            $serviceAssignment->getName()
        );

        foreach (
            [
                $notification,
                $serviceAssignment,
            ]
            as $route
        ) {
            $middleware = $route->gatherMiddleware();

            $this->assertContains(
                'auth:sanctum',
                $middleware
            );

            $this->assertContains(
                'user.active',
                $middleware
            );

            $this->assertContains(
                'identity.verified',
                $middleware
            );
        }
    }
}
