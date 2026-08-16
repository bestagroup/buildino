<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use App\Models\UserDevice;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileBootstrapApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_login_can_sync_mobile_device_without_breaking_existing_token_response(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $owner =
            $this->user(
                'role.owner@buildino.local'
            );

        $response =
            $this->postJson(
                '/api/v1/auth/password/login',
                [
                    'login' =>
                        $owner->mobile,

                    'password' =>
                        'Demo@1405',

                    'device_name' =>
                        'Owner Android',

                    'device_id' =>
                        'owner-mobile-device-1',

                    'platform' =>
                        'android',

                    'push_token' =>
                        'owner-fcm-token-1',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $owner->getKey()
            )
            ->assertJsonPath(
                'token_type',
                'Bearer'
            );

        $this->assertNotEmpty(
            $response->json(
                'access_token'
            )
        );

        $this->assertDatabaseHas(
            'user_devices',
            [
                'user_id' =>
                    $owner->getKey(),

                'device_id' =>
                    'owner-mobile-device-1',

                'platform' =>
                    'android',

                'push_token' =>
                    'owner-fcm-token-1',
            ]
        );
    }

    public function test_owner_mobile_bootstrap_returns_only_resident_scope_and_device_state(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $owner =
            $this->user(
                'role.owner@buildino.local'
            );

        UserDevice::query()
            ->create([
                'user_id' =>
                    $owner->getKey(),

                'device_id' =>
                    'owner-bootstrap-device',

                'platform' =>
                    'android',

                'device_name' =>
                    'Owner Phone',

                'push_token' =>
                    'owner-bootstrap-token',

                'last_used_at' =>
                    now(),
            ]);

        Sanctum::actingAs(
            $owner,
            [
                'api',
            ]
        );

        $response =
            $this
                ->withHeaders([
                    'X-Device-Id' =>
                        'owner-bootstrap-device',

                    'X-App-Version' =>
                        '0.9.0',
                ])
                ->getJson(
                    '/api/v1/mobile/bootstrap'
                );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.user.id',
                $owner->getKey()
            )
            ->assertJsonPath(
                'data.device.registered',
                true
            )
            ->assertJsonPath(
                'data.device.push_enabled',
                true
            )
            ->assertJsonPath(
                'data.app.upgrade_required',
                true
            )
            ->assertJsonPath(
                'data.resident.enabled',
                true
            );

        $personas =
            $response->json(
                'data.personas',
                []
            );

        $this->assertContains(
            'resident',
            $personas
        );

        $units =
            collect(
                $response->json(
                    'data.resident.units',
                    []
                )
            )
                ->pluck(
                    'unit_number'
                )
                ->all();

        $this->assertContains(
            '101',
            $units
        );

        $this->assertNotContains(
            '102',
            $units
        );
    }

    public function test_provider_mobile_bootstrap_exposes_provider_persona_without_resident_scope(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $provider =
            $this->user(
                'role.provider@buildino.local'
            );

        Sanctum::actingAs(
            $provider,
            [
                'api',
            ]
        );

        $response =
            $this->getJson(
                '/api/v1/mobile/bootstrap'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.provider.enabled',
                true
            )
            ->assertJsonPath(
                'data.resident.enabled',
                false
            );

        $personas =
            $response->json(
                'data.personas',
                []
            );

        $this->assertContains(
            'provider',
            $personas
        );

        $this->assertNotContains(
            'resident',
            $personas
        );
    }

    public function test_logout_releases_current_mobile_device(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $owner =
            $this->user(
                'role.owner@buildino.local'
            );

        $token =
            $owner
                ->createToken(
                    'Owner Device',
                    [
                        'api',
                    ]
                )
                ->plainTextToken;

        UserDevice::query()
            ->create([
                'user_id' =>
                    $owner->getKey(),

                'device_id' =>
                    'owner-logout-device',

                'platform' =>
                    'android',

                'push_token' =>
                    'owner-logout-token',

                'last_used_at' =>
                    now(),
            ]);

        $this
            ->withToken(
                $token
            )
            ->postJson(
                '/api/v1/auth/logout',
                [
                    'device_id' =>
                        'owner-logout-device',
                ]
            )
            ->assertNoContent();

        $this->assertDatabaseMissing(
            'user_devices',
            [
                'device_id' =>
                    'owner-logout-device',
            ]
        );
    }

    private function user(
        string $email
    ): User {
        return User::query()
            ->where(
                'email',
                $email
            )
            ->firstOrFail();
    }
}
