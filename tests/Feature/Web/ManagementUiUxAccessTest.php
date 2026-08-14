<?php

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Role;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class ManagementUiUxAccessTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBuildingDomainData;

    public function test_scoped_building_manager_sees_building_navigation_but_not_global_user_admin(): void
    {
        $graph =
            $this->createBuildingGraph();

        $manager =
            $this->createUser();

        $this->grant(
            $manager->id,
            [
                'reports.dashboard.view',
                'buildings.view',
                'blocks.view',
                'floors.view',
                'units.view',
                'facility-reservations.view',
                'invoices.view',
            ],
            $graph['building']
        );

        $this->actingAs(
            $manager,
            'web'
        );

        $this->get(
            '/management'
        )
            ->assertOk()
            ->assertSee(
                'ساختار مجتمع'
            )
            ->assertSee(
                'مالی و کیف پول'
            )
            ->assertDontSee(
                'کاربران و دسترسی'
            )
            ->assertSee(
                'ساختمان '
                . $graph[
                    'building'
                ]->title
            );

        $this->get(
            '/management/operations/buildings'
        )
            ->assertOk();

        $this->get(
            '/management/operations/users'
        )
            ->assertForbidden();
    }

    public function test_global_administrator_sees_user_access_navigation_and_role_scope_identity(): void
    {
        $this->createBuildingGraph();

        $admin =
            $this->createUser();

        $this->grant(
            $admin->id,
            [
                'reports.platform.view',
                'users.view',
                'users.create',
                'users.update',
                'users.delete',
                'complexes.view',
                'buildings.view',
                'system.health.view',
            ],
            null
        );

        $this->actingAs(
            $admin,
            'web'
        );

        $this->get(
            '/management/operations'
        )
            ->assertOk()
            ->assertSee(
                'کاربران و دسترسی'
            )
            ->assertSee(
                'دسترسی سراسری'
            )
            ->assertSee(
                'کاربران'
            );

        $this->get(
            '/management/operations/users'
        )
            ->assertOk();
    }

    private function grant(
        int $userId,
        array $permissionNames,
        $scope
    ): void {
        $role =
            Role::query()
                ->create([
                    'name' =>
                        'ui-role-'
                        . uniqid(),
                    'display_name' =>
                        $scope
                            ? 'مدیر ساختمان'
                            : 'مدیر سراسری',
                    'is_system' =>
                        false,
                ]);

        $permissionIds =
            collect(
                $permissionNames
            )
                ->map(
                    function (
                        string $name
                    ): int {
                        return Permission::query()
                            ->firstOrCreate(
                                [
                                    'name' =>
                                        $name,
                                ],
                                [
                                    'display_name' =>
                                        $name,
                                    'module' =>
                                        str(
                                            $name
                                        )
                                            ->before('.')
                                            ->toString(),
                                ]
                            )
                            ->getKey();
                    }
                )
                ->all();

        $role
            ->permissions()
            ->sync(
                $permissionIds
            );

        UserRoleAssignment::query()
            ->create([
                'user_id' =>
                    $userId,
                'role_id' =>
                    $role->getKey(),
                'scope_type' =>
                    $scope
                        ? $scope
                            ->getMorphClass()
                        : null,
                'scope_id' =>
                    $scope
                        ? $scope
                            ->getKey()
                        : null,
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
