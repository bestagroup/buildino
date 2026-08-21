<?php

namespace Tests\Feature\Web;

use App\Models\ManagedUserScope;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Database\Seeders\RoleMatrixSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class ScopedUserManagementWebTest extends TestCase
{
    use CreatesBuildingDomainData;
    use RefreshDatabase;

    public function test_complex_building_and_block_managers_can_define_users_in_their_own_scope(): void
    {
        $graph = $this->createBuildingGraph();

        foreach (
            [
                'complex' => $graph['complex'],
                'building' => $graph['building'],
                'block' => $graph['block'],
            ] as $key => $scope
        ) {
            $manager = $this->createUser([
                'mobile' => '09120000'
                    .str_pad(
                        (string) (100 + strlen($key)),
                        3,
                        '0',
                        STR_PAD_LEFT
                    ),
                'mobile_verified_at' => now(),
            ]);

            $this->grant(
                $manager,
                [
                    'reports.dashboard.view',
                    'users.view',
                    'users.create',
                ],
                $scope,
                "{$key}-user-manager"
            );

            $this->actingAs($manager, 'web');

            $this->get('/management/operations/users')
                ->assertOk()
                ->assertSee('ثبت رکورد جدید');

            $response = $this->postJson(
                '/management/data/users',
                [
                    'first_name' => 'کاربر',
                    'last_name' => $key,
                    'mobile' => '09990000'
                        .str_pad(
                            (string) (100 + strlen($key)),
                            3,
                            '0',
                            STR_PAD_LEFT
                        ),
                    'password' => 'Password@123',
                    'verify_mobile' => true,
                ]
            )->assertCreated();

            $createdUserId = (int) $response->json('data.id');

            $this->assertDatabaseHas(
                'managed_user_scopes',
                [
                    'user_id' => $createdUserId,
                    'scope_type' => $scope->getMorphClass(),
                    'scope_id' => $scope->getKey(),
                    'assigned_by' => $manager->getKey(),
                ]
            );

            $this->getJson('/management/data/users?per_page=100')
                ->assertOk()
                ->assertJsonFragment([
                    'id' => $createdUserId,
                ]);

            $this->getJson('/management/lookups/users')
                ->assertOk()
                ->assertJsonFragment([
                    'id' => $createdUserId,
                ]);
        }
    }

    public function test_scoped_manager_cannot_see_or_modify_a_user_from_another_scope(): void
    {
        $ownGraph = $this->createBuildingGraph();
        $otherGraph = $this->createBuildingGraph();
        $manager = $this->createUser([
            'mobile_verified_at' => now(),
        ]);

        $this->grant(
            $manager,
            [
                'reports.dashboard.view',
                'users.view',
                'users.update',
            ],
            $ownGraph['building'],
            'isolated-user-manager'
        );

        $outsideUser = $this->createUser();

        ManagedUserScope::query()->create([
            'user_id' => $outsideUser->getKey(),
            'scope_type' => $otherGraph['building']->getMorphClass(),
            'scope_id' => $otherGraph['building']->getKey(),
        ]);

        $this->actingAs($manager, 'web');

        $this->getJson('/management/data/users?per_page=100')
            ->assertOk()
            ->assertJsonMissing([
                'id' => $outsideUser->getKey(),
            ]);

        $this->patchJson(
            "/management/data/users/{$outsideUser->getKey()}",
            [
                'first_name' => 'غیرمجاز',
            ]
        )->assertForbidden();
    }

    public function test_global_admin_can_assign_the_block_manager_role_to_a_block_scope(): void
    {
        $this->seed(RoleMatrixSeeder::class);

        $graph = $this->createBuildingGraph();
        $admin = $this->createUser([
            'mobile_verified_at' => now(),
        ]);
        $target = $this->createUser();
        $superadmin = Role::query()
            ->where('name', 'superadmin')
            ->firstOrFail();
        $blockManager = Role::query()
            ->where('name', 'block_manager')
            ->firstOrFail();

        UserRoleAssignment::query()->create([
            'user_id' => $admin->getKey(),
            'role_id' => $superadmin->getKey(),
            'scope_type' => null,
            'scope_id' => null,
            'starts_at' => now()->subMinute(),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'web');

        $this->postJson(
            '/management/data/role-assignments',
            [
                'user_id' => $target->getKey(),
                'role_id' => $blockManager->getKey(),
                'scope_type' => 'block',
                'scope_id' => $graph['block']->getKey(),
                'is_active' => true,
            ]
        )
            ->assertCreated()
            ->assertJsonPath('data.scope_type', 'block')
            ->assertJsonPath(
                'data.scope_id',
                $graph['block']->getKey()
            );

        $this->assertDatabaseHas(
            'user_role_assignments',
            [
                'user_id' => $target->getKey(),
                'role_id' => $blockManager->getKey(),
                'scope_type' => $graph['block']->getMorphClass(),
                'scope_id' => $graph['block']->getKey(),
            ]
        );
    }

    private function grant(
        User $user,
        array $permissionNames,
        Model $scope,
        string $roleName
    ): void {
        $role = Role::query()->create([
            'name' => $roleName,
            'display_name' => $roleName,
            'is_system' => false,
        ]);

        $role->permissions()->sync(
            collect($permissionNames)
                ->map(
                    fn (string $name): int => Permission::query()->firstOrCreate(
                        ['name' => $name],
                        [
                            'display_name' => $name,
                            'module' => str($name)
                                ->before('.')
                                ->toString(),
                        ]
                    )->getKey()
                )
                ->all()
        );

        UserRoleAssignment::query()->create([
            'user_id' => $user->getKey(),
            'role_id' => $role->getKey(),
            'scope_type' => $scope->getMorphClass(),
            'scope_id' => $scope->getKey(),
            'starts_at' => now()->subMinute(),
            'is_active' => true,
        ]);
    }
}
