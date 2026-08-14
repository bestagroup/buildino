<?php

namespace Tests\Feature\System;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FinalRouteRegistrationTest extends TestCase
{
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
