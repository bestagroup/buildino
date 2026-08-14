<?php

namespace Tests\Feature\Security;

use App\Models\Building;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BuildingHttpAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_building_scoped_user_can_read_assigned_building(): void
    {
        $user = User::factory()->create();

        $building = Building::factory()->create();

        $role = $this->createRoleWithPermission(
            'manager',
            'buildings.view'
        );

        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,

            'scope_type' => $building->getMorphClass(),
            'scope_id' => $building->id,

            'starts_at' => now()->subDay(),
            'ends_at' => null,

            'is_active' => true,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Diagnostic Assertions
        |--------------------------------------------------------------------------
        */

        $this->assertTrue(
            app(\App\Services\Security\BuildingAccessService::class)
                ->allows($user, $building),
            'BuildingAccessService denied the assigned building.'
        );

        $this->assertTrue(
            app(\App\Support\Authorization\PermissionChecker::class)
                ->allows(
                    $user,
                    'buildings.view',
                    $building
                ),
            'PermissionChecker denied buildings.view for the assigned building.'
        );

        $this->assertTrue(
            $user->can('view', $building),
            'BuildingPolicy denied view permission.'
        );

        /*
        |--------------------------------------------------------------------------
        | HTTP Request
        |--------------------------------------------------------------------------
        */

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/v1/buildings/{$building->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $building->id
            );
    }

    public function test_building_scoped_user_cannot_read_another_building(): void
    {
        $user = User::factory()->create();

        $allowedBuilding = Building::factory()->create();
        $otherBuilding = Building::factory()->create();

        $role = $this->createRoleWithPermission(
            'manager',
            'buildings.view'
        );

        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,

            'scope_type' => $allowedBuilding->getMorphClass(),
            'scope_id' => $allowedBuilding->id,

            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            "/api/v1/buildings/{$otherBuilding->id}"
        )
            ->assertForbidden()
            ->assertJsonPath(
                'code',
                'BUILDING_ACCESS_DENIED'
            );
    }

    public function test_user_without_buildings_view_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $building = Building::factory()->create();

        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'is_system' => true,
        ]);

        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,

            'scope_type' => $building->getMorphClass(),
            'scope_id' => $building->id,

            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            "/api/v1/buildings/{$building->id}"
        )->assertForbidden();
    }

    public function test_global_role_can_read_any_building(): void
    {
        $user = User::factory()->create();

        $buildingA = Building::factory()->create();
        $buildingB = Building::factory()->create();

        $role = $this->createRoleWithPermission(
            'admin',
            'buildings.view'
        );

        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,

            'scope_type' => null,
            'scope_id' => null,

            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            "/api/v1/buildings/{$buildingA->id}"
        )->assertOk();

        $this->getJson(
            "/api/v1/buildings/{$buildingB->id}"
        )->assertOk();
    }

    private function createRoleWithPermission(
        string $roleName,
        string $permissionName
    ): Role {
        $role = Role::query()->create([
            'name' => $roleName,
            'display_name' => ucfirst($roleName),
            'is_system' => true,
        ]);

        $permission = Permission::query()->create([
            'name' => $permissionName,
            'display_name' => $permissionName,
            'module' => 'buildings',
        ]);

        $role->permissions()->attach(
            $permission->id
        );

        return $role;
    }
}
