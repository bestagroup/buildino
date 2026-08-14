<?php

namespace Tests\Feature\Security;

use App\Enums\OccupancyType;
use App\Enums\UnitUsageType;
use App\Models\Block;
use App\Models\Building;
use App\Models\Complex;
use App\Models\Floor;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\UnitOwnership;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\Security\BuildingAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnitResidencyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_building_manager_can_manage_ownership_and_occupancy_only_inside_scope(): void
    {
        $manager = $this->createUser(
            '09120001001',
            'manager@example.test'
        );

        $resident = $this->createUser(
            '09120001002',
            'resident@example.test'
        );

        $structureA = $this->createStructure('A');
        $structureB = $this->createStructure('B');

        $role = $this->createRoleWithPermissions(
            'residency-manager',
            [
                'unit-ownerships.view',
                'unit-ownerships.create',
                'unit-ownerships.update',

                'unit-occupancies.view',
                'unit-occupancies.create',
                'unit-occupancies.update',
            ]
        );

        $this->assignRole(
            $manager,
            $role,
            $structureA['building']
        );

        Sanctum::actingAs($manager);

        $ownershipResponse = $this->postJson(
            "/api/v1/units/{$structureA['unit']->id}/ownerships",
            [
                'user_id' => $resident->id,
                'ownership_percentage' => 100,
                'starts_at' => now()->toDateString(),
                'is_primary' => true,

                // Must be ignored because unit comes from route.
                'unit_id' => $structureB['unit']->id,
            ]
        );

        $ownershipResponse
            ->assertCreated()
            ->assertJsonPath(
                'data.unit_id',
                $structureA['unit']->id
            );

        $this->postJson(
            "/api/v1/units/{$structureB['unit']->id}/ownerships",
            [
                'user_id' => $resident->id,
                'ownership_percentage' => 100,
                'starts_at' => now()->toDateString(),
            ]
        )->assertForbidden();

        $occupancyResponse = $this->postJson(
            "/api/v1/units/{$structureA['unit']->id}/occupancies",
            [
                'user_id' => $resident->id,
                'occupancy_type' => OccupancyType::Tenant->value,
                'starts_at' => now()->toDateString(),
                'is_primary' => true,

                // Must be ignored because unit comes from route.
                'unit_id' => $structureB['unit']->id,
            ]
        );

        $occupancyResponse
            ->assertCreated()
            ->assertJsonPath(
                'data.unit_id',
                $structureA['unit']->id
            );

        $this->postJson(
            "/api/v1/units/{$structureB['unit']->id}/occupancies",
            [
                'user_id' => $resident->id,
                'occupancy_type' => OccupancyType::Tenant->value,
                'starts_at' => now()->toDateString(),
            ]
        )->assertForbidden();
    }

    public function test_ending_ownership_and_occupancy_records_actor_and_deactivates_relation(): void
    {
        $manager = $this->createUser(
            '09120002001',
            'manager2@example.test'
        );

        $resident = $this->createUser(
            '09120002002',
            'resident2@example.test'
        );

        $structure = $this->createStructure('END');

        $role = $this->createRoleWithPermissions(
            'residency-end-manager',
            [
                'unit-ownerships.update',
                'unit-occupancies.update',
            ]
        );

        $this->assignRole(
            $manager,
            $role,
            $structure['building']
        );

        $ownership = UnitOwnership::query()->create([
            'unit_id' => $structure['unit']->id,
            'user_id' => $resident->id,
            'ownership_percentage' => 100,
            'starts_at' => now()->subMonth()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        $occupancy = UnitOccupancy::query()->create([
            'unit_id' => $structure['unit']->id,
            'user_id' => $resident->id,
            'occupancy_type' => OccupancyType::Owner->value,
            'starts_at' => now()->subMonth()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        Sanctum::actingAs($manager);

        $this->postJson(
            "/api/v1/unit-ownerships/{$ownership->id}/end",
            [
                'ends_at' => now()->toDateString(),
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.ended_by', $manager->id);

        $this->postJson(
            "/api/v1/unit-occupancies/{$occupancy->id}/end",
            [
                'ends_at' => now()->toDateString(),
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.ended_by', $manager->id);

        $this->assertDatabaseHas(
            'unit_ownerships',
            [
                'id' => $ownership->id,
                'is_active' => false,
                'ended_by' => $manager->id,
            ]
        );

        $this->assertDatabaseHas(
            'unit_occupancies',
            [
                'id' => $occupancy->id,
                'is_active' => false,
                'ended_by' => $manager->id,
            ]
        );
    }

    public function test_only_one_active_primary_ownership_and_occupancy_exists_per_unit(): void
    {
        $manager = $this->createUser(
            '09120003001',
            'manager3@example.test'
        );

        $userA = $this->createUser(
            '09120003002',
            'a@example.test'
        );

        $userB = $this->createUser(
            '09120003003',
            'b@example.test'
        );

        $structure = $this->createStructure('PRIMARY');

        $role = $this->createRoleWithPermissions(
            'primary-manager',
            [
                'unit-ownerships.create',
                'unit-occupancies.create',
            ]
        );

        $this->assignRole(
            $manager,
            $role,
            $structure['building']
        );

        Sanctum::actingAs($manager);

        foreach ([$userA, $userB] as $user) {
            $this->postJson(
                "/api/v1/units/{$structure['unit']->id}/ownerships",
                [
                    'user_id' => $user->id,
                    'ownership_percentage' => 50,
                    'starts_at' => now()->toDateString(),
                    'is_primary' => true,
                ]
            )->assertCreated();
        }

        $this->assertSame(
            1,
            UnitOwnership::query()
                ->where('unit_id', $structure['unit']->id)
                ->where('is_primary', true)
                ->where('is_active', true)
                ->count()
        );

        foreach ([$userA, $userB] as $user) {
            $this->postJson(
                "/api/v1/units/{$structure['unit']->id}/occupancies",
                [
                    'user_id' => $user->id,
                    'occupancy_type' => OccupancyType::Resident->value,
                    'starts_at' => now()->toDateString(),
                    'is_primary' => true,
                ]
            )->assertCreated();
        }

        $this->assertSame(
            1,
            UnitOccupancy::query()
                ->where('unit_id', $structure['unit']->id)
                ->where('is_primary', true)
                ->where('is_active', true)
                ->count()
        );
    }

    public function test_total_active_ownership_percentage_cannot_exceed_one_hundred(): void
    {
        $manager = $this->createUser(
            '09120004001',
            'manager4@example.test'
        );

        $userA = $this->createUser(
            '09120004002',
            'owner-a@example.test'
        );

        $userB = $this->createUser(
            '09120004003',
            'owner-b@example.test'
        );

        $structure = $this->createStructure('PERCENT');

        $role = $this->createRoleWithPermissions(
            'ownership-percentage-manager',
            [
                'unit-ownerships.create',
            ]
        );

        $this->assignRole(
            $manager,
            $role,
            $structure['building']
        );

        Sanctum::actingAs($manager);

        $this->postJson(
            "/api/v1/units/{$structure['unit']->id}/ownerships",
            [
                'user_id' => $userA->id,
                'ownership_percentage' => 60,
                'starts_at' => now()->toDateString(),
            ]
        )->assertCreated();

        $this->postJson(
            "/api/v1/units/{$structure['unit']->id}/ownerships",
            [
                'user_id' => $userB->id,
                'ownership_percentage' => 50,
                'starts_at' => now()->toDateString(),
            ]
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'ownership_percentage'
            );
    }

    public function test_active_owner_or_occupant_grants_building_access_service_access(): void
    {
        $resident = $this->createUser(
            '09120005001',
            'resident-access@example.test'
        );

        $structure = $this->createStructure('ACCESS');

        UnitOccupancy::query()->create([
            'unit_id' => $structure['unit']->id,
            'user_id' => $resident->id,
            'occupancy_type' => OccupancyType::Resident->value,
            'starts_at' => now()->toDateString(),
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->assertTrue(
            app(BuildingAccessService::class)->allows(
                $resident,
                $structure['building']
            )
        );
    }

    private function createUser(
        string $mobile,
        string $email
    ): User {
        return User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'mobile' => $mobile,
            'email' => $email,
            'mobile_verified_at' => now(),
            'email_verified_at' => now(),
            'password' => 'TestPassword123!',
            'is_active' => true,
            'is_blocked' => false,
        ]);
    }

    private function createStructure(
        string $suffix
    ): array {
        $complex = Complex::query()->create([
            'code' => "CMP-{$suffix}",
            'title' => "Complex {$suffix}",
            'province' => 'Tehran',
            'city' => 'Tehran',
            'is_active' => true,
        ]);

        $building = Building::query()->create([
            'complex_id' => $complex->id,
            'code' => "BLD-{$suffix}",
            'title' => "Building {$suffix}",
            'is_active' => true,
        ]);

        $block = Block::query()->create([
            'building_id' => $building->id,
            'title' => "Block {$suffix}",
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $floor = Floor::query()->create([
            'block_id' => $block->id,
            'floor_number' => 1,
            'title' => "Floor {$suffix}",
            'sort_order' => 1,
        ]);

        $unit = Unit::query()->create([
            'floor_id' => $floor->id,
            'unit_number' => "101-{$suffix}",
            'title' => "Unit {$suffix}",
            'area' => 100,
            'bedrooms' => 2,
            'usage_type' => UnitUsageType::cases()[0]->value,
            'is_active' => true,
        ]);

        return compact(
            'complex',
            'building',
            'block',
            'floor',
            'unit'
        );
    }

    private function createRoleWithPermissions(
        string $name,
        array $permissionNames
    ): Role {
        $role = Role::query()->create([
            'name' => $name,
            'display_name' => $name,
            'is_system' => true,
        ]);

        foreach ($permissionNames as $permissionName) {
            $module = explode('.', $permissionName)[0];

            $permission = Permission::query()->firstOrCreate(
                [
                    'name' => $permissionName,
                ],
                [
                    'display_name' => $permissionName,
                    'module' => $module,
                ]
            );

            $role->permissions()->syncWithoutDetaching([
                $permission->id,
            ]);
        }

        return $role;
    }

    private function assignRole(
        User $user,
        Role $role,
        mixed $scope
    ): UserRoleAssignment {
        return UserRoleAssignment::query()->create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => $scope->getMorphClass(),
            'scope_id' => $scope->getKey(),
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'is_active' => true,
            'assigned_by' => null,
        ]);
    }
}
