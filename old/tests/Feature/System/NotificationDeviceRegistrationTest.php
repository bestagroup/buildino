<?php

namespace Tests\Feature\System;

use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class NotificationDeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBuildingDomainData;

    public function test_authenticated_verified_user_can_register_notification_device(): void
    {
        $user = $this->createUser([
            'mobile_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            '/api/v1/notification-devices',
            [
                'device_id' => 'notification-device-regression-1',
                'platform' => 'android',
                'device_name' => 'Regression Phone',
                'push_token' => 'notification-push-token-regression-1',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.user_id',
                $user->id
            )
            ->assertJsonPath(
                'data.device_id',
                'notification-device-regression-1'
            );

        $this->assertDatabaseHas(
            'user_devices',
            [
                'user_id' => $user->id,
                'device_id' => 'notification-device-regression-1',
                'push_token' => 'notification-push-token-regression-1',
            ]
        );

        $device = UserDevice::query()
            ->where(
                'device_id',
                'notification-device-regression-1'
            )
            ->firstOrFail();

        $this->assertSame(
            $user->id,
            $device->user_id
        );
    }
}
