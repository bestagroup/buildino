<?php

namespace Tests\Feature\System;

use App\Enums\OccupancyType;
use App\Enums\PaymentStatus;
use App\Enums\ReportStatus;
use App\Jobs\Reports\GenerateReportJob;
use App\Models\Permission;
use App\Models\ReportDefinition;
use App\Models\Role;
use App\Models\UnitOccupancy;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class ReleaseCandidateApiFlowTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBuildingDomainData;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment_gateways.gateways.fake' => [
                'driver' => 'fake',
                'enabled' => true,
                'webhook_secret' =>
                    'buildino-rc-test-secret',
            ],

            'payment_gateways.callback_base_url' =>
                'https://buildino.test',
        ]);
    }

    public function test_resident_can_top_up_wallet_through_gateway_and_replayed_callback_does_not_double_credit(): void
    {
        $graph =
            $this->createBuildingGraph();

        $resident =
            $this->createUser([
                'mobile_verified_at' =>
                    now(),
            ]);

        UnitOccupancy::query()->create([
            'unit_id' =>
                $graph['unit']->id,

            'user_id' =>
                $resident->id,

            'occupancy_type' =>
                OccupancyType::Resident,

            'starts_at' =>
                now()->toDateString(),

            'ends_at' =>
                null,

            'is_primary' => true,
            'is_active' => true,
        ]);

        Sanctum::actingAs(
            $resident
        );

        $this->getJson(
            '/api/v1/auth/me'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $resident->id
            );

        $topUp = $this->postJson(
            '/api/v1/buildings/'
            .$graph['building']->id
            .'/wallet-topups',
            [
                'target_type' =>
                    'user_wallet',

                'amount' =>
                    500_000,

                'method' =>
                    'online',

                'gateway' =>
                    'fake',

                'idempotency_key' =>
                    'rc-e2e-wallet-topup',
            ]
        );

        $topUp
            ->assertCreated()
            ->assertJsonPath(
                'data.amount',
                500_000
            )
            ->assertJsonPath(
                'data.status',
                'pending'
            );

        $paymentId = (int) $topUp
            ->json(
                'data.payment_id'
            );

        $this->assertGreaterThan(
            0,
            $paymentId
        );

        $initiation =
            $this->postJson(
                '/api/v1/payments/'
                .$paymentId
                .'/gateway/initiate',
                [
                    'gateway' =>
                        'fake',

                    'idempotency_key' =>
                        'rc-e2e-wallet-topup',
                ]
            );

        $initiation
            ->assertOk()
            ->assertJsonPath(
                'data.gateway',
                'fake'
            );

        $authority =
            $initiation->json(
                'data.authority'
            );

        $this->assertNotEmpty(
            $authority
        );

        $callbackUrl =
            '/api/v1/payment-gateways/fake/callback'
            .'?authority='
            .rawurlencode(
                (string) $authority
            );

        $this->getJson(
            $callbackUrl
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'processed'
            )
            ->assertJsonPath(
                'data.payment.status',
                'paid'
            );

        $wallet =
            $this->getJson(
                '/api/v1/wallets/me'
            );

        $wallet
            ->assertOk()
            ->assertJsonPath(
                'data.balance',
                500_000
            )
            ->assertJsonPath(
                'data.available_balance',
                500_000
            );

        /*
         * Browser refresh / PSP retry:
         * same callback cannot create a second top-up credit.
         */
        $this->getJson(
            $callbackUrl
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'processed'
            );

        $this->getJson(
            '/api/v1/wallets/me'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.balance',
                500_000
            );

        $this->assertDatabaseHas(
            'payments',
            [
                'id' => $paymentId,
                'status' =>
                    PaymentStatus::Paid->value,
            ]
        );

        $this->assertDatabaseCount(
            'payment_gateway_events',
            1
        );
    }

    public function test_scoped_manager_can_read_financial_report_and_queue_export_through_http_contract(): void
    {
        Queue::fake();

        $graph =
            $this->createBuildingGraph();

        $manager =
            $this->createUser([
                'mobile_verified_at' =>
                    now(),
            ]);

        $this->grantBuildingPermissions(
            $manager,
            $graph['building'],
            [
                'reports.financial.view',
                'generated-reports.create',
                'generated-reports.view',
            ]
        );

        Sanctum::actingAs(
            $manager
        );

        $this->getJson(
            '/api/v1/buildings/'
            .$graph['building']->id
            .'/reports/financial-summary'
        )
            ->assertOk()
            ->assertJsonPath(
                'data.building_id',
                $graph['building']->id
            );

        $definition =
            ReportDefinition::query()
                ->create([
                    'code' =>
                        'building.financial_summary',

                    'title' =>
                        'Building Financial Summary',

                    'module' =>
                        'financial',

                    'configuration' => [
                        'permission' =>
                            'reports.financial.view',
                        'scope' =>
                            'building',
                    ],

                    'is_active' =>
                        true,
                ]);

        $response =
            $this->postJson(
                '/api/v1/report-definitions/'
                .$definition->id
                .'/exports',
                [
                    'building_id' =>
                        $graph['building']->id,

                    'format' =>
                        'csv',

                    'from' =>
                        now()
                            ->startOfMonth()
                            ->toDateString(),

                    'to' =>
                        now()
                            ->toDateString(),
                ]
            );

        $response
            ->assertStatus(202)
            ->assertJsonPath(
                'data.status',
                ReportStatus::Pending->value
            )
            ->assertJsonPath(
                'data.building_id',
                $graph['building']->id
            );

        $generatedReportId =
            (int) $response->json(
                'data.id'
            );

        $this->assertGreaterThan(
            0,
            $generatedReportId
        );

        Queue::assertPushed(
            GenerateReportJob::class,
            fn (GenerateReportJob $job): bool =>
                $job->generatedReportId
                    === $generatedReportId
        );

        $this->getJson(
            '/api/v1/report-exports/'
            .$generatedReportId
        )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $generatedReportId
            );
    }

    public function test_public_readiness_and_protected_contract_boundary_are_enforced(): void
    {
        $readiness = $this->getJson(
            '/api/v1/system/readiness'
        );

        $readiness
            ->assertOk()
            ->assertJsonPath(
                'data.ready',
                true
            );

        $this->assertSame(
            config(
                'api_contract_v1.version'
            ),
            $readiness->headers->get(
                'X-Buildino-API-Version'
            )
        );

        $this->getJson(
            '/api/v1/wallets/me'
        )->assertUnauthorized();
    }

    private function grantBuildingPermissions(
        $user,
        $building,
        array $permissionNames
    ): void {
        $role = Role::query()->create([
            'name' =>
                'rc-manager-'
                .uniqid(),

            'display_name' =>
                'RC Manager',

            'is_system' =>
                false,
        ]);

        $permissionIds = [];

        foreach (
            $permissionNames
            as $name
        ) {
            $permission =
                Permission::query()
                    ->firstOrCreate(
                        [
                            'name' => $name,
                        ],
                        [
                            'display_name' =>
                                $name,
                            'module' =>
                                'rc',
                        ]
                    );

            $permissionIds[] =
                $permission->id;
        }

        $role->permissions()
            ->sync(
                $permissionIds
            );

        UserRoleAssignment::query()
            ->create([
                'user_id' =>
                    $user->id,

                'role_id' =>
                    $role->id,

                'scope_type' =>
                    $building
                        ->getMorphClass(),

                'scope_id' =>
                    $building->id,

                'starts_at' =>
                    now()
                        ->subMinute(),

                'ends_at' =>
                    null,

                'is_active' =>
                    true,

                'assigned_by' =>
                    null,
            ]);
    }
}
