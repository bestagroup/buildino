<?php

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Role;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class ManagementUiUxAccessTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBuildingDomainData;

    public function test_management_processes_have_separate_sidebar_links(): void
    {
        $graph = $this->createBuildingGraph();
        $manager = $this->createUser();
        $resources = ['ownerships', 'occupancies', 'invitations', 'guest-visits', 'facilities', 'reservations', 'charge-formulas', 'charge-periods'];
        $permissions = array_map(fn (string $key): string => config("management_crud.resources.$key.permission"), $resources);
        $this->grant($manager->id, ['reports.dashboard.view', ...$permissions], $graph['building']);

        $response = $this->actingAs($manager, 'web')->get('/management')->assertOk();
        foreach ($resources as $resource) {
            $response->assertSee('data-nav-key="'.$resource.'"', false);
            $response->assertSee(route('management.operations.show', $resource), false);
        }
    }

    #[DataProvider('limitedNavigationResources')]
    public function test_sidebar_links_match_the_accessible_process(string $resource): void
    {
        $graph = $this->createBuildingGraph();
        $manager = $this->createUser();
        $this->grant($manager->id, [
            'reports.dashboard.view', config("management_crud.resources.$resource.permission"),
        ], $graph['building']);

        $response = $this->actingAs($manager, 'web')->get('/management')->assertOk();
        $response->assertSee('data-nav-key="'.$resource.'"', false);
        foreach (['ownerships', 'occupancies', 'invitations', 'guest-visits', 'facilities', 'reservations', 'charge-formulas', 'charge-periods'] as $other) {
            if ($other !== $resource) {
                $response->assertDontSee('data-nav-key="'.$other.'"', false);
            }
        }
        $response->assertDontSee('data-nav-key="finance"', false);
    }

    public static function limitedNavigationResources(): array
    {
        return [['ownerships'], ['invitations'], ['reservations'], ['charge-formulas'], ['charge-periods']];
    }

    public function test_invitation_page_reuses_crud_with_a_separate_inline_editor(): void
    {
        $graph = $this->createBuildingGraph();
        $manager = $this->createUser();
        $this->grant($manager->id, ['reports.dashboard.view', 'unit-invitations.view', 'unit-invitations.create'], $graph['building']);

        $this->actingAs($manager, 'web')->get('/management/operations/invitations')
            ->assertOk()
            ->assertSee('data-invitation-location', false)
            ->assertSee('data-invitation-directory', false)
            ->assertSee('data-inline-editor', false)
            ->assertSee('buildino-invitations.js', false)
            ->assertSee('دعوت جدید')
            ->assertDontSee('crud-sibling-nav', false);
    }

    public function test_structure_pages_share_the_new_interface_and_scope_contexts_and_actions(): void
    {
        $inside = $this->createBuildingGraph();
        $outside = $this->createBuildingGraph();
        $manager = $this->createUser();
        $this->grant($manager->id, ['reports.dashboard.view', 'buildings.view', 'buildings.update', 'blocks.view', 'blocks.create', 'floors.view', 'units.view'], $inside['building']);
        $this->actingAs($manager, 'web');
        $this->get('/management/operations/buildings')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('management/operations/Resource')
            ->where('structureUi.abilities.'.$inside['building']->id.'.update', true)
            ->missing('structureUi.abilities.'.$outside['building']->id)
            ->where('structureUi.canCreate', false));
        foreach (['blocks', 'floors', 'units'] as $resource) {
            $this->get('/management/operations/'.$resource)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('management/operations/Resource')
                ->where('resourceKey', $resource)
                ->has('structureUi.contexts', 1)
                ->where('structureUi.contexts.0.building_id', $inside['building']->id)
                ->where('structureUi.contexts.0.create', $resource === 'blocks')
                ->where('structureUi.contexts.0.delete', false));
        }
    }

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
                'ساختار ساختمان'
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
