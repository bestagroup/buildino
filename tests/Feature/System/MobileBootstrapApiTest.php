<?php

namespace Tests\Feature\System;

use App\Models\User;
use App\Models\UserDevice;
use Database\Seeders\AccessScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileBootstrapApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_bootstrap_returns_only_resident_units_and_device_state(): void
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
                    'owner-android-1',

                'platform' =>
                    'android',

                'device_name' =>
                    'Owner Phone',

                'push_token' =>
                    'owner-push-token',

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
                    'X-Device-ID' =>
                        'owner-android-1',

                    'X-Platform' =>
                        'android',

                    'X-App-Build' =>
                        '10',
                ])
                ->getJson(
                    '/api/v1/mobile/bootstrap'
                );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.capabilities.resident',
                true
            )
            ->assertJsonPath(
                'data.capabilities.provider',
                false
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
                'data.resident.units.0.unit_number',
                '101'
            );

        $unitNumbers =
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
            $unitNumbers
        );

        $this->assertNotContains(
            '102',
            $unitNumbers
        );
    }

    public function test_provider_bootstrap_exposes_provider_capability_without_resident_scope(): void
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

        $this->getJson(
            '/api/v1/mobile/bootstrap'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.capabilities.provider',
                true
            )
            ->assertJsonPath(
                'data.capabilities.resident',
                false
            )
            ->assertJsonStructure([
                'data' => [
                    'provider' => [
                        'active_jobs',
                        'jobs_endpoint',
                        'bank_accounts_endpoint',
                        'payouts_endpoint',
                    ],
                ],
            ]);
    }

    public function test_management_user_bootstrap_returns_only_accessible_management_buildings(): void
    {
        $this->seed(
            AccessScenarioSeeder::class
        );

        $manager =
            $this->user(
                'role.building@buildino.local'
            );

        Sanctum::actingAs(
            $manager,
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
                'data.capabilities.management',
                true
            );

        $this->assertCount(
            1,
            $response->json(
                'data.management.buildings',
                []
            )
        );
    }

    public function test_logout_with_device_header_revokes_current_device_registration(): void
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
                    'logout-device',

                'platform' =>
                    'android',

                'device_name' =>
                    'Logout Phone',

                'push_token' =>
                    'logout-token',

                'last_used_at' =>
                    now(),
            ]);

        $token =
            $owner
                ->createToken(
                    'mobile-test',
                    [
                        'api',
                    ]
                )
                ->plainTextToken;

        $this
            ->withToken(
                $token
            )
            ->withHeader(
                'X-Device-ID',
                'logout-device'
            )
            ->postJson(
                '/api/v1/auth/logout'
            )
            ->assertNoContent();

        $this->assertDatabaseMissing(
            'user_devices',
            [
                'user_id' =>
                    $owner->getKey(),

                'device_id' =>
                    'logout-device',
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
