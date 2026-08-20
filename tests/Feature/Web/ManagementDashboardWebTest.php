<?php

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Role;
use App\Models\UserRoleAssignment;
use App\Services\System\RuntimeHeartbeatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class ManagementDashboardWebTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBuildingDomainData;

    public function test_guest_is_redirected_to_management_login(): void
    {
        $this->get(
            '/management'
        )
            ->assertRedirect(
                '/management/login'
            );
    }

    public function test_scoped_manager_can_login_and_view_only_authorized_building_dashboard(): void
    {
        $graph =
            $this->createBuildingGraph();

        $other =
            $this->createBuildingGraph();

        /*
         * CreatesBuildingDomainData intentionally uses deterministic titles
         * ("Test Building") for generic domain tests. This test must distinguish
         * the authorized building from the unauthorized one in rendered HTML,
         * otherwise assertSee()/assertDontSee() target the exact same string.
         */
        $graph['building']->update([
            'title' =>
                'Authorized Dashboard Building',
        ]);

        $other['building']->update([
            'title' =>
                'Unauthorized Dashboard Building',
        ]);

        $manager =
            $this->createUser();

        $this->grantPermission(
            $manager->id,
            $graph['building'],
            'reports.dashboard.view'
        );

        $this->post(
            '/management/login',
            [
                'login' =>
                    $manager->mobile,
                'password' =>
                    'password',
                'remember' =>
                    '0',
            ]
        )
            ->assertRedirect(
                route(
                    'management.dashboard'
                )
            );

        $this->assertAuthenticatedAs(
            $manager,
            'web'
        );

        $response =
            $this->get(
                '/management'
            );

        $response
            ->assertOk()
            ->assertSee(
                'داشبورد مدیریتی'
            )
            ->assertSee(
                $graph['building']->title
            )
            ->assertDontSee(
                $other['building']->title
            )
            ->assertSee(
                'ماژول‌های سامانه'
            )
            ->assertSee(
                'سلامت سامانه'
            );
    }

    public function test_user_without_management_report_permission_cannot_enter_dashboard(): void
    {
        $this->createBuildingGraph();

        $user =
            $this->createUser();

        $this->from(
            '/management/login'
        )
            ->post(
                '/management/login',
                [
                    'login' =>
                        $user->mobile,
                    'password' =>
                        'password',
                    'remember' =>
                        '0',
                ]
            )
            ->assertRedirect(
                '/management/login'
            )
            ->assertSessionHasErrors(
                'login'
            );

        $this->assertGuest(
            'web'
        );
    }

    public function test_platform_report_permission_exposes_platform_overview_and_building_selector(): void
    {
        $first =
            $this->createBuildingGraph();

        $second =
            $this->createBuildingGraph();

        $first['building']->update([
            'title' =>
                'Platform Building Alpha',
        ]);

        $second['building']->update([
            'title' =>
                'Platform Building Beta',
        ]);

        $admin =
            $this->createUser();

        $this->grantPermission(
            $admin->id,
            null,
            'reports.platform.view'
        );

        $this->actingAs(
            $admin,
            'web'
        );

        $response =
            $this->get(
                '/management'
            );

        $response
            ->assertOk()
            ->assertSee(
                'مرکز مدیریت Buildino'
            )
            ->assertSee(
                $first['building']->title
            )
            ->assertSee(
                $second['building']->title
            )
            ->assertSee(
                'نمای کل پلتفرم'
            );
    }

    public function test_dashboard_renders_scheduler_queue_and_system_health_as_ok_with_fresh_runtime(): void
    {
        $this->createBuildingGraph();

        $admin = $this->createUser();

        $this->grantPermission(
            $admin->id,
            null,
            'reports.platform.view'
        );
        $this->grantPermission(
            $admin->id,
            null,
            'system.health.view'
        );

        app(RuntimeHeartbeatService::class)
            ->touch('scheduler');

        $this->actingAs(
            $admin,
            'web'
        );

        $this->get('/management')
            ->assertOk()
            ->assertSee(
                'health-pill health-pill--ok',
                false
            )
            ->assertSee('سالم')
            ->assertSee('Scheduler')
            ->assertSee('Queue')
            ->assertSee(
                'health-text health-text--ok',
                false
            );
    }

    private function grantPermission(
        int $userId,
        $scope,
        string $permissionName
    ): void {
        $role = Role::query()->create([
            'name' =>
                'web-dashboard-'
                .uniqid(),
            'display_name' =>
                'Web Dashboard',
            'is_system' =>
                false,
        ]);

        $permission =
            Permission::query()
                ->firstOrCreate(
                    [
                        'name' =>
                            $permissionName,
                    ],
                    [
                        'display_name' =>
                            $permissionName,
                        'module' =>
                            'reports',
                    ]
                );

        $role->permissions()
            ->sync([
                $permission->id,
            ]);

        UserRoleAssignment::query()
            ->create([
                'user_id' =>
                    $userId,
                'role_id' =>
                    $role->id,
                'scope_type' =>
                    $scope?->getMorphClass(),
                'scope_id' =>
                    $scope?->getKey(),
                'starts_at' =>
                    now()->subMinute(),
                'ends_at' =>
                    null,
                'is_active' =>
                    true,
                'assigned_by' =>
                    null,
            ]);
    }
}
