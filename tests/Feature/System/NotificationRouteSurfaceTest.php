<?php

namespace Tests\Feature\System;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NotificationRouteSurfaceTest extends TestCase
{
    public function test_complete_notification_api_surface_is_registered_directly(): void
    {
        $routes = [
            ['GET', '/api/v1/notifications', 'api.v1.notifications.index'],
            ['GET', '/api/v1/notifications/unread-count', 'api.v1.notifications.unread-count'],
            ['POST', '/api/v1/notifications/read-all', 'api.v1.notifications.read-all'],
            ['POST', '/api/v1/notifications/123/read', 'api.v1.notifications.read'],
            ['GET', '/api/v1/notification-devices', 'api.v1.notification-devices.index'],
            ['POST', '/api/v1/notification-devices', 'api.v1.notification-devices.store'],
            ['DELETE', '/api/v1/notification-devices/123', 'api.v1.notification-devices.destroy'],
            ['GET', '/api/v1/notification-preferences', 'api.v1.notification-preferences.index'],
            ['PUT', '/api/v1/notification-preferences', 'api.v1.notification-preferences.update'],
        ];

        foreach ($routes as [$method, $uri, $name]) {
            $route = Route::getRoutes()->match(
                Request::create($uri, $method)
            );

            $this->assertSame(
                $name,
                $route->getName(),
                "Unexpected route matched for {$method} {$uri}"
            );

            $this->assertStringContainsString(
                'UserNotificationController@',
                $route->getActionName()
            );

            $middleware = $route->gatherMiddleware();

            $this->assertContains('auth:sanctum', $middleware);
            $this->assertContains('user.active', $middleware);
            $this->assertContains('identity.verified', $middleware);
            $this->assertContains('throttle:notifications', $middleware);
        }
    }
}
