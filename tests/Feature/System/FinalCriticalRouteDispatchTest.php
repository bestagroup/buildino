<?php

namespace Tests\Feature\System;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FinalCriticalRouteDispatchTest extends TestCase
{
    public function test_critical_final_routes_match_the_expected_runtime_contract(): void
    {
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

        $assignment = Route::getRoutes()->match(
            Request::create(
                '/api/v1/service-requests/123/assign',
                'POST'
            )
        );

        $this->assertSame(
            'api.v1.service-requests.assign',
            $assignment->getName()
        );

        $this->assertSame(
            'api/v1/service-requests/{serviceRequest}/assign',
            $assignment->uri()
        );

        $this->assertStringContainsString(
            'UserNotificationController@registerDevice',
            $notification->getActionName()
        );

        $this->assertStringContainsString(
            'ServiceRequestOperationController@assign',
            $assignment->getActionName()
        );

        foreach ([$notification, $assignment] as $route) {
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
