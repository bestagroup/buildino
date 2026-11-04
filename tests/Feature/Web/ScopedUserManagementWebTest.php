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
use Inertia\Testing\AssertableInertia as Assert;

class ScopedUserManagementWebTest extends TestCase
{
    use CreatesBuildingDomainData;
    use RefreshDatabase;


    public function test_building_manager_can_edit_existing_building_users(): void
    {
        $this->seed(RoleMatrixSeeder::class);
        $graph = $this->createBuildingGraph();
        $manager = $this->createUser(['mobile_verified_at' => now()]);
        $target = $this->createUser();
        UserRoleAssignment::query()->create([
            'user_id' => $manager->id,
            'role_id' => Role::query()->where('name', 'building_manager')->value('id'),
            'scope_type' => $graph['building']->getMorphClass(),
            'scope_id' => $graph['building']->id,
            'is_active' => true,
        ]);
        ManagedUserScope::query()->create([
            'user_id' => $target->id,
            'scope_type' => $graph['building']->getMorphClass(),
            'scope_id' => $graph['building']->id,
        ]);
        $this->actingAs($manager, 'web')
            ->get('/management/operations/users')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('management/access/Users')
                ->where('resource.update.method', 'PATCH')->missing('resource.delete'));
        $this->patchJson('/management/data/users/'.$target->id, [
            'first_name' => 'نام اصلاح‌شده',
            'last_name' => 'کاربر ساختمان',
        ])->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $target->id, 'first_name' => 'نام اصلاح‌شده',
            'last_name' => 'کاربر ساختمان',
        ]);
        $outside = $this->createUser();
        $this->patchJson('/management/data/users/'.$outside->id, [
            'first_name' => 'غیرمجاز',
        ])->assertForbidden();
    }

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
                ->assertInertia(fn (Assert $page) => $page->component('management/access/Users')->where('workspace.canCreate', true));

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

    public function test_user_list_filters_status_and_exposes_only_scoped_row_actions(): void
    {
        $a = $this->createBuildingGraph();
        $b = $this->createBuildingGraph();
        $actor = $this->createUser();
        $editable = $this->createUser(['is_active' => false]);
        $readOnly = $this->createUser(['is_blocked' => true]);
        $foreign = $this->createUser();
        $this->grant($actor, ['reports.dashboard.view', 'users.view', 'users.update'], $a['building'], 'users-editor-a');
        $this->grant($actor, ['users.view'], $b['building'], 'users-viewer-b');
        foreach ([[$editable, $a['building']], [$readOnly, $b['building']]] as [$target, $building]) {
            ManagedUserScope::query()->create(['user_id' => $target->id, 'scope_type' => $building->getMorphClass(), 'scope_id' => $building->id]);
        }
        $rows = collect($this->actingAs($actor)->getJson('/management/data/users')->assertOk()->json('data'))->keyBy('id');
        $this->assertTrue($rows[$editable->id]['can_update']);
        $this->assertFalse($rows[$editable->id]['can_delete']);
        $this->assertFalse($rows[$readOnly->id]['can_update']);
        $this->assertFalse($rows[$readOnly->id]['can_delete']);
        $this->assertFalse($rows->has($foreign->id));
        $this->getJson('/management/data/users?status=inactive')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $editable->id);
        $this->getJson('/management/data/users?status=blocked')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $readOnly->id);
        $this->getJson('/management/data/users?status=invalid')->assertUnprocessable();
        $this->patchJson('/management/data/users/'.$readOnly->id, ['first_name' => 'Forbidden'])->assertForbidden();
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
