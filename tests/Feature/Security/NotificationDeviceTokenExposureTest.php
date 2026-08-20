<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class NotificationDeviceTokenExposureTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBuildingDomainData;

    public function test_device_registration_and_listing_never_return_raw_push_token(): void
    {
        $user = $this->createUser([
            'mobile_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $register = $this->postJson(
            '/api/v1/notification-devices',
            [
                'device_id' => 'secure-device-1',
                'platform' => 'android',
                'device_name' => 'Secure Phone',
                'push_token' => 'raw-secret-device-token',
            ]
        );

        $register->assertCreated();
        $this->assertArrayNotHasKey(
            'push_token',
            $register->json('data')
        );

        $deviceId = (int) $register->json('data.id');

        $index = $this->getJson(
            '/api/v1/notification-devices'
        );

        $index->assertOk();
        $this->assertArrayNotHasKey(
            'push_token',
            $index->json('data.0')
        );

        $update = $this->patchJson(
            '/api/v1/notification-devices/'.$deviceId,
            [
                'push_token' => 'rotated-secret-device-token',
            ]
        );

        $update->assertOk();
        $this->assertArrayNotHasKey(
            'push_token',
            $update->json('data')
        );

        $this->assertDatabaseHas(
            'user_devices',
            [
                'id' => $deviceId,
                'user_id' => $user->getKey(),
                'push_token' => 'rotated-secret-device-token',
            ]
        );
    }
}
