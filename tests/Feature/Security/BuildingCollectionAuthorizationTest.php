<?php

namespace Tests\Feature\Security;

use App\Models\Building;
use App\Models\Complex;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BuildingCollectionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_user_can_view_all_buildings(): void
    {
        $user = User::factory()->create();

        $role = $this->createRoleWithPermission(
            'admin',
            'buildings.view'
        );

        $this->assignRole(
            user: $user,
            role: $role
        );

        $buildingA = Building::factory()->create();
        $buildingB = Building::factory()->create();
        $buildingC = Building::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/buildings');

        $response->assertOk();

        $ids = collect($response->json('data'))
            ->pluck('id');

        $this->assertTrue(
            $ids->contains($buildingA->id)
        );

        $this->assertTrue(
            $ids->contains($buildingB->id)
        );

        $this->assertTrue(
            $ids->contains($buildingC->id)
        );
    }

    public function test_complex_scoped_user_sees_only_buildings_of_assigned_complex(): void
    {
        $user = User::factory()->create();

        $role = $this->createRoleWithPermission(
            'complex-manager',
            'buildings.view'
        );

        $complexA = Complex::factory()->create();
        $complexB = Complex::factory()->create();

        $buildingA1 = Building::factory()
            ->for($complexA)
            ->create();

        $buildingA2 = Building::factory()
            ->for($complexA)
            ->create();

        $buildingB1 = Building::factory()
            ->for($complexB)
            ->create();

        $this->assignRole(
            user: $user,
            role: $role,
            scope: $complexA
        );

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/buildings');

        $response->assertOk();

        $ids = collect($response->json('data'))
            ->pluck('id');

        $this->assertTrue(
            $ids->contains($buildingA1->id)
        );

        $this->assertTrue(
            $ids->contains($buildingA2->id)
        );

        $this->assertFalse(
            $ids->contains($buildingB1->id)
        );

        $this->assertCount(
            2,
            $ids
        );
    }

    public function test_building_scoped_user_sees_only_assigned_building(): void
    {
        $user = User::factory()->create();

        $role = $this->createRoleWithPermission(
            'building-manager',
            'buildings.view'
        );

        $buildingA = Building::factory()->create();
        $buildingB = Building::factory()->create();
        $buildingC = Building::factory()->create();

        $this->assignRole(
            user: $user,
            role: $role,
            scope: $buildingA
        );

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/buildings');

        $response->assertOk();

        $ids = collect($response->json('data'))
            ->pluck('id');

        $this->assertSame(
            [$buildingA->id],
            $ids->values()->all()
        );

        $this->assertFalse(
            $ids->contains($buildingB->id)
        );

        $this->assertFalse(
            $ids->contains($buildingC->id)
        );
    }

    public function test_user_without_buildings_view_permission_receives_empty_collection(): void
    {
        $user = User::factory()->create();

        Building::factory()
            ->count(3)
            ->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/buildings');

        $response
            ->assertOk()
            ->assertJsonCount(
                0,
                'data'
            );
    }

    public function test_complex_scoped_user_can_filter_only_inside_own_complex(): void
    {
        $user = User::factory()->create();

        $role = $this->createRoleWithPermission(
            'complex-manager',
            'buildings.view'
        );

        $complexA = Complex::factory()->create();
        $complexB = Complex::factory()->create();

        $buildingA = Building::factory()
            ->for($complexA)
            ->create();

        Building::factory()
            ->for($complexB)
            ->create();

        $this->assignRole(
            user: $user,
            role: $role,
            scope: $complexA
        );

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/v1/buildings?complex_id={$complexB->id}"
        );

        $response
            ->assertOk()
            ->assertJsonCount(
                0,
                'data'
            );

        $response = $this->getJson(
            "/api/v1/buildings?complex_id={$complexA->id}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $buildingA->id
            );
    }

    public function test_complex_scoped_user_can_create_building_in_assigned_complex(): void
    {
        $user = User::factory()->create();

        $role = $this->createRoleWithPermission(
            'complex-building-creator',
            'buildings.create'
        );

        $complex = Complex::factory()->create();

        $this->assignRole(
            user: $user,
            role: $role,
            scope: $complex
        );

        Sanctum::actingAs($user);

        $payload = [
            'complex_id' => $complex->id,
            'code' => 'BLD-TEST-001',
            'title' => 'Test Building',
            'is_active' => true,
        ];

        $response = $this->postJson(
            '/api/v1/buildings',
            $payload
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.complex_id',
                $complex->id
            )
            ->assertJsonPath(
                'data.code',
                'BLD-TEST-001'
            );

        $this->assertDatabaseHas(
            'buildings',
            [
                'complex_id' => $complex->id,
                'code' => 'BLD-TEST-001',
            ]
        );
    }

    public function test_complex_scoped_user_cannot_create_building_in_another_complex(): void
    {
        $user = User::factory()->create();

        $role = $this->createRoleWithPermission(
            'complex-building-creator',
            'buildings.create'
        );

        $allowedComplex = Complex::factory()->create();
        $otherComplex = Complex::factory()->create();

        $this->assignRole(
            user: $user,
            role: $role,
            scope: $allowedComplex
        );

        Sanctum::actingAs($user);

        $response = $this->postJson(
            '/api/v1/buildings',
            [
                'complex_id' => $otherComplex->id,
                'code' => 'BLD-DENIED-001',
                'title' => 'Unauthorized Building',
            ]
        );

        $response->assertForbidden();

        $this->assertDatabaseMissing(
            'buildings',
            [
                'code' => 'BLD-DENIED-001',
            ]
        );
    }

    public function test_global_user_can_create_building_in_any_complex(): void
    {
        $user = User::factory()->create();

        $role = $this->createRoleWithPermission(
            'global-building-creator',
            'buildings.create'
        );

        $this->assignRole(
            user: $user,
            role: $role
        );

        $complexA = Complex::factory()->create();
        $complexB = Complex::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson(
            '/api/v1/buildings',
            [
                'complex_id' => $complexA->id,
                'code' => 'GLOBAL-A',
                'title' => 'Building A',
            ]
        )->assertCreated();

        $this->postJson(
            '/api/v1/buildings',
            [
                'complex_id' => $complexB->id,
                'code' => 'GLOBAL-B',
                'title' => 'Building B',
            ]
        )->assertCreated();

        $this->assertDatabaseHas(
            'buildings',
            [
                'complex_id' => $complexA->id,
                'code' => 'GLOBAL-A',
            ]
        );

        $this->assertDatabaseHas(
            'buildings',
            [
                'complex_id' => $complexB->id,
                'code' => 'GLOBAL-B',
            ]
        );
    }

    private function createRoleWithPermission(
        string $roleName,
        string $permissionName
    ): Role {
        $role = Role::query()->create([
            'name' => $roleName,
            'display_name' => $roleName,
            'is_system' => true,
        ]);

        $permission = Permission::query()->firstOrCreate(
            [
                'name' => $permissionName,
            ],
            [
                'display_name' => $permissionName,
                'module' => 'buildings',
            ]
        );

        $role->permissions()->syncWithoutDetaching([
            $permission->id,
        ]);

        return $role;
    }

    private function assignRole(
        User $user,
        Role $role,
        mixed $scope = null
    ): UserRoleAssignment {
        return UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,

            'scope_type' => $scope?->getMorphClass(),
            'scope_id' => $scope?->getKey(),

            'starts_at' => now()->subDay(),
            'ends_at' => null,

            'is_active' => true,

            'assigned_by' => null,
        ]);
    }
}
