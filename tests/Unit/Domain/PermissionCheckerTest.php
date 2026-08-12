<?php

namespace Tests\Unit\Domain;

use App\Models\Permission;
use App\Models\Role;
use App\Models\UserRoleAssignment;
use App\Support\Authorization\PermissionChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBuildingDomainData;
use Tests\TestCase;

class PermissionCheckerTest extends TestCase
{
    use RefreshDatabase, CreatesBuildingDomainData;

    public function test_global_role_permission_allows_action(): void
    {
        $user = $this->createUser();
        $permission = Permission::query()->create([
            'name' => 'buildings.view',
            'display_name' => 'View buildings',
            'module' => 'buildings',
        ]);
        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Manager',
        ]);
        $role->permissions()->attach($permission->id);

        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->assertTrue(app(PermissionChecker::class)->allows($user, 'buildings.view'));
    }

    public function test_blocked_user_is_denied_even_with_permission(): void
    {
        $user = $this->createUser(['is_blocked' => true]);
        $permission = Permission::query()->create([
            'name' => 'buildings.view',
            'display_name' => 'View buildings',
            'module' => 'buildings',
        ]);
        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Manager',
        ]);
        $role->permissions()->attach($permission->id);

        UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->assertFalse(app(PermissionChecker::class)->allows($user, 'buildings.view'));
    }
}
