<?php

namespace Tests\Feature\System;

use App\Models\Permission;
use App\Models\Role;
use App\Models\UserRoleAssignment;
use App\Services\System\RuntimeHeartbeatService;
use App\Services\System\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class ProductionReadinessFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBuildingDomainData;

    public function test_public_readiness_is_minimal_and_security_headers_are_applied(): void
    {
        $response = $this->withHeader(
            'X-Request-ID',
            str_repeat('A', 200)
        )->getJson(
            '/api/v1/system/readiness'
        );

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'ready',
                    'timestamp',
                ],
            ]);

        $this->assertNull(
            $response->json(
                'data.checks'
            )
        );

        $this->assertTrue(
            $response->json(
                'data.ready'
            )
        );

        $this->assertSame(
            'nosniff',
            $response->headers->get(
                'X-Content-Type-Options'
            )
        );

        $this->assertSame(
            'DENY',
            $response->headers->get(
                'X-Frame-Options'
            )
        );

        $this->assertStringContainsString(
            "default-src 'none'",
            (string) $response->headers->get(
                'Content-Security-Policy'
            )
        );

        $requestId =
            $response->headers->get(
                'X-Request-ID'
            );

        $this->assertNotSame(
            str_repeat('A', 200),
            $requestId
        );

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f-]{36}$/',
            (string) $requestId
        );
    }

    public function test_fresh_scheduler_and_queue_heartbeats_move_runtime_health_to_ok(): void
    {
        $heartbeats = app(
            RuntimeHeartbeatService::class
        );

        $heartbeats->touch(
            'scheduler'
        );

        foreach (
            config(
                'production_readiness.health.required_queues',
                ['default']
            )
            as $queue
        ) {
            $heartbeats->touch(
                'queue-worker:'.$queue,
                [
                    'queue' => $queue,
                ]
            );
        }

        $result = app(
            SystemHealthService::class
        )->inspect(
            true
        );

        $this->assertTrue(
            $result['ready']
        );

        $this->assertSame(
            'ok',
            $result['checks']['database']['status']
        );

        $this->assertSame(
            'ok',
            $result['checks']['cache']['status']
        );

        $this->assertSame(
            'ok',
            $result['checks']['storage']['status']
        );

        $this->assertSame(
            'ok',
            $result['checks']['scheduler']['status']
        );

        $this->assertSame(
            'ok',
            $result['checks']['queues']['status']
        );
    }

    public function test_failed_jobs_degrade_detailed_health_without_making_api_not_ready(): void
    {
        DB::table(
            'failed_jobs'
        )->insert([
            'uuid' =>
                'failed-job-test-uuid',
            'connection' =>
                'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' =>
                'Test exception',
            'failed_at' => now(),
        ]);

        $result = app(
            SystemHealthService::class
        )->inspect(
            true
        );

        $this->assertTrue(
            $result['ready']
        );

        $this->assertSame(
            'warning',
            $result['checks']['failed_jobs']['status']
        );

        $this->assertSame(
            1,
            $result['checks']['failed_jobs']['count']
        );

        $this->assertSame(
            'degraded',
            $result['status']
        );
    }

    public function test_admin_health_requires_explicit_global_permission(): void
    {
        $user = $this->createUser();

        Sanctum::actingAs(
            $user
        );

        $this->getJson(
            '/api/v1/admin/system/health'
        )->assertForbidden();

        $role = Role::query()->create([
            'name' =>
                'system-health-admin',
            'display_name' =>
                'System Health Admin',
            'is_system' => true,
        ]);

        $permission =
            Permission::query()
                ->firstOrCreate(
                    [
                        'name' =>
                            'system.health.view',
                    ],
                    [
                        'display_name' =>
                            'system.health.view',
                        'module' =>
                            'system',
                    ]
                );

        $role->permissions()
            ->syncWithoutDetaching([
                $permission->id,
            ]);

        UserRoleAssignment::query()
            ->create([
                'user_id' =>
                    $user->id,
                'role_id' =>
                    $role->id,
                'scope_type' =>
                    null,
                'scope_id' =>
                    null,
                'starts_at' =>
                    now()->subMinute(),
                'ends_at' =>
                    null,
                'is_active' =>
                    true,
                'assigned_by' =>
                    null,
            ]);

        $this->getJson(
            '/api/v1/admin/system/health'
        )
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'ready',
                    'environment',
                    'application',
                    'timestamp',
                    'checks' => [
                        'database',
                        'cache',
                        'storage',
                        'scheduler',
                        'queues',
                        'failed_jobs',
                        'domain',
                    ],
                ],
            ]);
    }

    public function test_runtime_infrastructure_and_production_query_indexes_exist(): void
    {
        foreach (
            [
                'cache',
                'cache_locks',
                'jobs',
                'failed_jobs',
                'job_batches',
                'system_runtime_heartbeats',
            ]
            as $table
        ) {
            $this->assertTrue(
                Schema::hasTable(
                    $table
                ),
                "Missing runtime table: {$table}"
            );
        }

        $this->assertTrue(
            Schema::hasIndex(
                'wallet_transfers',
                'wt_source_status_completed_idx'
            )
        );

        $this->assertTrue(
            Schema::hasIndex(
                'wallet_transfers',
                'wt_dest_status_completed_idx'
            )
        );

        $this->assertTrue(
            Schema::hasIndex(
                'unit_invoices',
                'ui_building_status_due_idx'
            )
        );
    }
}
